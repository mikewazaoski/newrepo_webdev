<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class ApiExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
            KernelEvents::REQUEST => ['onKernelRequest', 10] // High priority to run before security
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Only handle API routes
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }
        
        // Set custom error format for API requests
        $request->attributes->set('_api_format', 'json');
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API routes
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Get the root cause of the exception
        $rootException = $exception;
        while ($rootException->getPrevious()) {
            $rootException = $rootException->getPrevious();
        }

        // Handle authentication exceptions (check both current and root exception)
        if ($rootException instanceof \Symfony\Component\Security\Core\Exception\AuthenticationException ||
            $exception instanceof \Symfony\Component\Security\Core\Exception\AuthenticationException ||
            $rootException instanceof \Symfony\Component\Security\Core\Exception\AccessDeniedException ||
            $exception instanceof \Symfony\Component\Security\Core\Exception\AccessDeniedException) {
            $response = new JsonResponse([
                'status' => 401,
                'error' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
            
            $event->setResponse($response);
            return;
        }
    }
}
