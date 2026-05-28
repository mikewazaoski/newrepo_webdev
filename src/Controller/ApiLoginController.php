<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ApiTokenService;
use App\Service\MobileCustomerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ApiLoginController extends AbstractController
{
    public function __construct(
        private ApiTokenService $apiTokenService,
        private MobileCustomerService $mobileCustomerService,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST', 'GET'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return $this->json(['status' => 'error', 'message' => 'Invalid email or password'], 401);
        }

        if (!$user->isVerified()) {
            return $this->json([
                'status' => 'error',
                'message' => 'Email verification required',
                'requiresVerification' => true,
                'user' => $this->apiTokenService->serializeUser($user),
            ], 403);
        }

        $customer = $this->mobileCustomerService->getOrCreateForUser($user);

        return $this->json([
            'status' => 'success',
            'token' => $this->apiTokenService->generateToken($user),
            'user' => $this->apiTokenService->serializeUser($user),
            'customer' => $this->mobileCustomerService->serialize($customer),
        ]);
    }
}
