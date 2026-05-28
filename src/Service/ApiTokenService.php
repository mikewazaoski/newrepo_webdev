<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ApiTokenService
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $appSecret,
    ) {
    }

    public function generateToken(User $user): string
    {
        $payload = json_encode([
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'exp' => (new \DateTime('+7 days'))->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, $this->appSecret);

        return base64_encode($payload) . '.' . $signature;
    }

    /**
     * @return array{user_id: int, email: string, exp: int}|null
     */
    public function decodeToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (\count($parts) !== 2) {
            return $this->decodeLegacyToken($token);
        }

        [$encodedPayload, $signature] = $parts;
        $payloadJson = base64_decode($encodedPayload, true);
        if ($payloadJson === false) {
            return null;
        }

        $expected = hash_hmac('sha256', $payloadJson, $this->appSecret);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        return $this->parsePayload($payloadJson);
    }

    /**
     * @return array{user_id: int, email: string, exp: int}|null
     */
    private function decodeLegacyToken(string $token): ?array
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        return $this->parsePayload($decoded);
    }

    /**
     * @return array{user_id: int, email: string, exp: int}|null
     */
    private function parsePayload(string $payloadJson): ?array
    {
        $payload = json_decode($payloadJson, true);
        if (!\is_array($payload) || !isset($payload['user_id'], $payload['exp'])) {
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
