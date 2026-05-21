<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ApiTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ApiLoginController extends AbstractController
{
    public function __construct(
        private ApiTokenService $apiTokenService,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST', 'GET'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return $this->json(['message' => 'Invalid email or password'], 401);
        }

        return $this->json([
            'token' => $this->apiTokenService->generateToken($user),
            'user' => $this->apiTokenService->serializeUser($user),
        ]);
    }
}
