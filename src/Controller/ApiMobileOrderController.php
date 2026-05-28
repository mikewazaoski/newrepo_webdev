<?php

namespace App\Controller;

use App\Controller\Trait\ResolvesMobileUserTrait;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use App\Service\MobileCustomerService;
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
    use ResolvesMobileUserTrait;

    public function __construct(
        private ApiTokenService $apiTokenService,
        private UserRepository $userRepository,
        private MobileCustomerService $mobileCustomerService,
        private ProductRepository $productRepository,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private MobilePaymentService $mobilePaymentService,
    ) {
    }

    #[Route('/api/mobile/orders', name: 'api_mobile_orders_list', methods: ['GET'])]
    public function listOrders(Request $request): JsonResponse
    {
        $user = $this->resolveMobileUser($request, $this->apiTokenService, $this->userRepository);
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
            if ($order->getStatus() === 'pending') {
                $grouped[$ref]['status'] = 'pending';
            } elseif ($order->getStatus() === 'cancelled' && $grouped[$ref]['status'] !== 'pending') {
                $grouped[$ref]['status'] = 'cancelled';
            }
        }

        return $this->mobileJsonSuccess([
            'data' => array_values($grouped),
            'count' => count($grouped),
        ]);
    }

    #[Route('/api/mobile/orders', name: 'api_mobile_orders_create', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        $user = $this->resolveMobileUser($request, $this->apiTokenService, $this->userRepository);
        if (!$user instanceof User) {
            return $user;
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data) || !isset($data['items']) || !\is_array($data['items']) || $data['items'] === []) {
            return $this->mobileJsonError('Cart items are required');
        }

        $paymentMethod = isset($data['paymentMethod']) ? trim((string) $data['paymentMethod']) : MobilePaymentService::METHOD_COD;
        if ($paymentMethod !== MobilePaymentService::METHOD_COD) {
            return $this->mobileJsonError('Only cash on delivery is accepted');
        }

        $customer = $this->mobileCustomerService->getOrCreateForUser($user);
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
                return $this->mobileJsonError('Each item needs productId and quantity');
            }

            $product = $this->productRepository->find($productId);
            if (!$product instanceof Product) {
                return $this->mobileJsonError('Product not found: ' . $productId, Response::HTTP_NOT_FOUND);
            }

            if ($product->getStock() < $quantity) {
                return $this->mobileJsonError(sprintf(
                    'Not enough stock for "%s" (available: %d)',
                    $product->getName(),
                    $product->getStock()
                ));
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
            return $this->mobileJsonError('No valid items in order');
        }

        try {
            $payment = $this->mobilePaymentService->resolvePayment($paymentMethod, $runningTotal);
        } catch (\InvalidArgumentException $e) {
            return $this->mobileJsonError($e->getMessage());
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

        return $this->mobileJsonSuccess([
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
