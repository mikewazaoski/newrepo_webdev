<?php

namespace App\Service;

use App\Entity\User;

class ApiTokenService
{
    public function generateToken(User $user): string
    {
        $payload = [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'exp' => (new \DateTime('+24 hours'))->getTimestamp(),
        ];

        return base64_encode(json_encode($payload));
    }

    /**
     * @return array{user_id: int, email: string, exp: int}|null
     */
    public function decodeToken(string $token): ?array
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['user_id'], $payload['exp'])) {
            return null;
        }

        if ((int) $payload['exp'] < time()) {
            return null;
        }

        return [
            'user_id' => (int) $payload['user_id'],
            'email' => (string) ($payload['email'] ?? ''),
            'exp' => (int) $payload['exp'],
        ];
    }

    public function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
            'isVerified' => (bool) $user->isVerified(),
            'isActive' => $user->isActive(),
        ];
    }
}
