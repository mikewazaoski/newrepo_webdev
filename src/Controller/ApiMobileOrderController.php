<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use App\Service\MobilePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class ApiMobileOrderController extends AbstractController
{
    public function __construct(
        private ApiTokenService $apiTokenService,
        private UserRepository $userRepository,
        private CustomerRepository $customerRepository,
        private ProductRepository $productRepository,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private MobilePaymentService $mobilePaymentService,
    ) {}

    #[Route('/api/mobile/orders', name: 'api_mobile_orders_list', methods: ['GET'])]
    public function listOrders(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user instanceof User) {
            return $user;
        }

        $orders = $this->orderRepository->findBy(
            ['mobileUserId' => $user->getId()],
            ['orderDate' => 'DESC', 'id' => 'DESC'],
            200
        );

        $grouped = [];
        foreach ($orders as $order) {
            $ref = $order->getOrderRef() ?? 'legacy-' . $order->getId();
            if (!isset($grouped[$ref])) {
                $grouped[$ref] = [
                    'orderRef' => $order->getOrderRef(),
                    'status' => $order->getStatus(),
                    'orderDate' => $order->getOrderDate()?->format(\DateTimeInterface::ATOM),
                    'total' => 0.0,
                    'paymentMethod' => $order->getPaymentMethod(),
                    'paymentStatus' => $order->getPaymentStatus(),
                    'paymentReference' => $order->getPaymentReference(),
                    'paidAt' => $order->getPaidAt()?->format(\DateTimeInterface::ATOM),
                    'items' => [],
                ];
            }
            $lineTotal = (float) $order->getQuantity() * (float) $order->getPrice();
            $grouped[$ref]['total'] += $lineTotal;
            $grouped[$ref]['items'][] = $this->serializeOrderLine($order);
            // Use most restrictive status across lines (pending wins over shipped)
            if ($order->getStatus() === 'pending') {
                $grouped[$ref]['status'] = 'pending';
            } elseif ($order->getStatus() === 'cancelled' && $grouped[$ref]['status'] !== 'pending') {
                $grouped[$ref]['status'] = 'cancelled';
            }
        }

        return new JsonResponse([
            'status' => 'success',
            'data' => array_values($grouped),
            'count' => count($grouped),
        ]);
    }

    #[Route('/api/mobile/orders', name: 'api_mobile_orders_create', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user instanceof User) {
            return $user;
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data) || !isset($data['items']) || !\is_array($data['items']) || $data['items'] === []) {
            return new JsonResponse(['error' => 'Cart items are required'], Response::HTTP_BAD_REQUEST);
        }

        $paymentMethod = isset($data['paymentMethod']) ? trim((string) $data['paymentMethod']) : MobilePaymentService::METHOD_COD;
        if ($paymentMethod !== MobilePaymentService::METHOD_COD) {
            return new JsonResponse(['error' => 'Only cash on delivery is accepted'], Response::HTTP_BAD_REQUEST);
        }

        $customer = $this->resolveCustomerForUser($user);
        $orderRef = Uuid::v4()->toRfc4122();
        $created = [];
        $runningTotal = 0.0;

        foreach ($data['items'] as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $productId = isset($row['productId']) ? (int) $row['productId'] : 0;
            $quantity = isset($row['quantity']) ? (int) $row['quantity'] : 0;
            if ($productId <= 0 || $quantity <= 0) {
                return new JsonResponse(['error' => 'Each item needs productId and quantity'], Response::HTTP_BAD_REQUEST);
            }

            $product = $this->productRepository->find($productId);
            if (!$product instanceof Product) {
                return new JsonResponse(['error' => 'Product not found: ' . $productId], Response::HTTP_NOT_FOUND);
            }

            if ($product->getStock() < $quantity) {
                return new JsonResponse([
                    'error' => sprintf(
                        'Not enough stock for "%s" (available: %d)',
                        $product->getName(),
                        $product->getStock()
                    ),
                ], Response::HTTP_BAD_REQUEST);
            }

            $order = new Order();
            $order->setCustomer($customer);
            $order->setProductName($product->getName());
            $order->setQuantity((float) $quantity);
            $order->setPrice((float) $product->getPrice());
            $order->setStatus('pending');
            $order->setOrderDate(new \DateTime());
            $order->setOrderRef($orderRef);
            $order->setProductId($product->getId());
            $order->setMobileUserId($user->getId());
            $order->setCreatedBy($user);

            $runningTotal += (float) $product->getPrice() * $quantity;
            $this->entityManager->persist($order);
            $created[] = $order;
        }

        if ($created === []) {
            return new JsonResponse(['error' => 'No valid items in order'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payment = $this->mobilePaymentService->resolvePayment($paymentMethod, $runningTotal);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        foreach ($created as $order) {
            $this->mobilePaymentService->applyPaymentToOrder($order, $payment);
        }

        $this->entityManager->flush();

        $lines = [];
        $total = 0.0;
        foreach ($created as $order) {
            $lines[] = $this->serializeOrderLine($order);
            $total += (float) $order->getQuantity() * (float) $order->getPrice();
        }

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Order placed! Pay with cash on delivery. Waiting for approval before shipping.',
            'orderRef' => $orderRef,
            'orderStatus' => 'pending',
            'total' => $total,
            'paymentMethod' => $payment['method'],
            'paymentStatus' => $payment['status'],
            'paymentReference' => $payment['reference'],
            'paidAt' => $payment['paidAt']?->format(\DateTimeInterface::ATOM),
            'items' => $lines,
        ], Response::HTTP_CREATED);
    }

    private function resolveUser(Request $request): User|JsonResponse
    {
        $header = $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return new JsonResponse(['error' => 'Authentication token required'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->apiTokenService->decodeToken($matches[1]);
        if (!$payload) {
            return new JsonResponse(['error' => 'Invalid or expired token'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->userRepository->find($payload['user_id']);
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return $user;
    }

    private function resolveCustomerForUser(User $user): Customer
    {
        $customer = $this->customerRepository->findOneBy(['email' => $user->getEmail()]);
        if ($customer instanceof Customer) {
            return $customer;
        }

        $username = $user->getUsername() ?? ('mobile_' . $user->getId());
        $existingUsername = $this->customerRepository->findOneBy(['username' => $username]);
        if ($existingUsername instanceof Customer) {
            $username = $username . '_' . $user->getId();
        }

        $customer = new Customer();
        $customer->setEmail($user->getEmail());
        $customer->setName($user->getName() ?? $user->getEmail());
        $customer->setCustomerName($user->getName() ?? 'Mobile customer');
        $customer->setUsername($username);
        $customer->setCreatedBy($user);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }

    private function serializeOrderLine(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'productName' => $order->getProductName(),
            'productId' => $order->getProductId(),
            'quantity' => (float) $order->getQuantity(),
            'price' => (float) $order->getPrice(),
            'lineTotal' => (float) $order->getQuantity() * (float) $order->getPrice(),
            'status' => $order->getStatus(),
            'orderRef' => $order->getOrderRef(),
            'orderDate' => $order->getOrderDate()?->format(\DateTimeInterface::ATOM),
            'paymentMethod' => $order->getPaymentMethod(),
            'paymentStatus' => $order->getPaymentStatus(),
            'paymentReference' => $order->getPaymentReference(),
        ];
    }
}
