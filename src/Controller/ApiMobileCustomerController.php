<?php

namespace App\Controller;

use App\Controller\Trait\ResolvesMobileUserTrait;
use App\Entity\Customer;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use App\Service\MobileCustomerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mobile/customer')]
class ApiMobileCustomerController extends AbstractController
{
    use ResolvesMobileUserTrait;

    public function __construct(
        private ApiTokenService $apiTokenService,
        private UserRepository $userRepository,
        private MobileCustomerService $mobileCustomerService,
        private OrderRepository $orderRepository,
    ) {
    }

    /** GET /api/mobile/customer — authenticated customer profile */
    #[Route('', name: 'api_mobile_customer_profile', methods: ['GET'])]
    public function profile(Request $request): JsonResponse
    {
        $user = $this->resolveMobileUser($request, $this->apiTokenService, $this->userRepository);
        if (!$user instanceof User) {
            return $user;
        }

        $customer = $this->mobileCustomerService->findForUser($user);
        if (!$customer instanceof Customer) {
            return $this->mobileJsonSuccess([
                'data' => null,
                'message' => 'No customer profile yet. Call POST /api/mobile/customer/sync after login.',
            ]);
        }

        return $this->mobileJsonSuccess([
            'data' => $this->mobileCustomerService->serialize($customer),
        ]);
    }

    /** PUT /api/mobile/customer — update name, phone, customerName */
    #[Route('', name: 'api_mobile_customer_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        $user = $this->resolveMobileUser($request, $this->apiTokenService, $this->userRepository);
        if (!$user instanceof User) {
            return $user;
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->mobileJsonError('Invalid JSON body');
        }

        if (
            !isset($data['name'])
            && !array_key_exists('phone', $data)
            && !isset($data['customerName'])
        ) {
            return $this->mobileJsonError('Provide at least one of: name, phone, customerName');
        }

        $customer = $this->mobileCustomerService->getOrCreateForUser($user);
        $customer = $this->mobileCustomerService->updateProfile($customer, $data);

        return $this->mobileJsonSuccess([
            'message' => 'Customer profile updated',
            'data' => $this->mobileCustomerService->serialize($customer),
        ]);
    }

    /** POST /api/mobile/customer/sync — create/link customer after login or register */
    #[Route('/sync', name: 'api_mobile_customer_sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        $user = $this->resolveMobileUser($request, $this->apiTokenService, $this->userRepository);
        if (!$user instanceof User) {
            return $user;
        }

        $customer = $this->mobileCustomerService->getOrCreateForUser($user);

        return $this->mobileJsonSuccess([
            'message' => 'Customer profile synced',
            'data' => $this->mobileCustomerService->serialize($customer),
        ], Response::HTTP_CREATED);
    }

    /** GET /api/mobile/customer/orders — order history for the authenticated customer */
    #[Route('/orders', name: 'api_mobile_customer_orders', methods: ['GET'])]
    public function orders(Request $request): JsonResponse
    {
        $user = $this->resolveMobileUser($request, $this->apiTokenService, $this->userRepository);
        if (!$user instanceof User) {
            return $user;
        }

        $customer = $this->mobileCustomerService->findForUser($user);
        if (!$customer instanceof Customer) {
            return $this->mobileJsonSuccess(['data' => [], 'count' => 0]);
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
                    'items' => [],
                ];
            }
            $lineTotal = (float) $order->getQuantity() * (float) $order->getPrice();
            $grouped[$ref]['total'] += $lineTotal;
            $grouped[$ref]['items'][] = [
                'id' => $order->getId(),
                'productName' => $order->getProductName(),
                'productId' => $order->getProductId(),
                'quantity' => (float) $order->getQuantity(),
                'price' => (float) $order->getPrice(),
                'lineTotal' => $lineTotal,
                'status' => $order->getStatus(),
            ];
        }

        return $this->mobileJsonSuccess([
            'data' => array_values($grouped),
            'count' => count($grouped),
        ]);
    }
}
