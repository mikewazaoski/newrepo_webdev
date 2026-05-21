<?php

namespace App\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\HttpFoundation\Response;

class ErrorNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public function normalize($exception, string $format = null, array $context = []): array
    {
        // Only handle 401/403 errors for API routes
        if (isset($context['request']) && str_starts_with($context['request']->getPathInfo(), '/api')) {
            $statusCode = $exception->getStatus() ?? 500;
            
            if ($statusCode === Response::HTTP_UNAUTHORIZED) {
                return [
                    'status' => 401,
                    'error' => 'Unauthorized'
                ];
            }
            
            if ($statusCode === Response::HTTP_FORBIDDEN) {
                return [
                    'status' => 403,
                    'error' => 'Forbidden'
                ];
            }
        }
        
        return [
            'status' => $exception->getStatus() ?? 500,
            'error' => $exception->getTitle() ?? 'Error'
        ];
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof \ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException 
            || (isset($context['type']) && $context['type'] === 'hydra:error');
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
