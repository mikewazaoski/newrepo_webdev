<?php

namespace App\Controller;

use App\Service\MobilePaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ApiMobilePaymentController extends AbstractController
{
    #[Route('/api/mobile/payment-methods', name: 'api_mobile_payment_methods', methods: ['GET'])]
    public function paymentMethods(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'success',
            'data' => [
                [
                    'id' => MobilePaymentService::METHOD_COD,
                    'label' => 'Cash on delivery',
                    'description' => 'Pay with cash when your order is delivered.',
                    'requiresReference' => false,
                ],
            ],
        ]);
    }
}
