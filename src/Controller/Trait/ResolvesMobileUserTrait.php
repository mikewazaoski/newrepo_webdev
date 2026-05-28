<?php

namespace App\Controller\Trait;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait ResolvesMobileUserTrait
{
    private function mobileJsonError(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return new JsonResponse(['status' => 'error', 'message' => $message], $status);
    }

    private function mobileJsonSuccess(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(array_merge(['status' => 'success'], $data), $status);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function resolveMobileUser(
        Request $request,
        ApiTokenService $apiTokenService,
        UserRepository $userRepository,
        bool $requireVerified = true,
    ): User|JsonResponse {
        $token = $this->extractBearerToken($request);
        if (!$token) {
            return $this->mobileJsonError('Authentication token required', Response::HTTP_UNAUTHORIZED);
        }

        $payload = $apiTokenService->decodeToken($token);
        if (!$payload) {
            return $this->mobileJsonError('Invalid or expired token', Response::HTTP_UNAUTHORIZED);
        }

        $user = $userRepository->find($payload['user_id']);
        if (!$user instanceof User) {
            return $this->mobileJsonError('User not found', Response::HTTP_NOT_FOUND);
        }

        if (!$user->isActive()) {
            return $this->mobileJsonError('Account is inactive', Response::HTTP_FORBIDDEN);
        }

        if ($requireVerified && !$user->isVerified()) {
            return $this->mobileJsonError('Email verification required', Response::HTTP_FORBIDDEN);
        }

        return $user;
    }
}
