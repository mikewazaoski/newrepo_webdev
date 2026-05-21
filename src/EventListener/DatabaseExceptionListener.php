<?php

namespace App\EventListener;

use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Prevent HTTP 500 when login/register hit an unreachable database.
 */
class DatabaseExceptionListener implements EventSubscriberInterface
{
    private const AUTH_PATHS = ['/login', '/register'];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->isDatabaseFailure($event->getThrowable())) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!in_array($path, self::AUTH_PATHS, true)) {
            return;
        }

        $request = $event->getRequest();
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add(
                'error',
                'Cannot reach the database. In Railway, add MySQL and set DATABASE_URL to ${{MySQL.MYSQL_URL}} on the app service, then redeploy.'
            );
        }

        $route = $path === '/register' ? 'app_register' : 'app_login';
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate($route)));
    }

    private function isDatabaseFailure(\Throwable $throwable): bool
    {
        while (true) {
            if ($throwable instanceof DBALException) {
                return true;
            }

            $message = strtolower($throwable->getMessage());
            if (
                str_contains($message, 'connection refused')
                || str_contains($message, 'connection timed out')
                || str_contains($message, 'getaddrinfo')
                || str_contains($message, 'access denied for user')
                || str_contains($message, 'unknown database')
                || str_contains($message, 'server has gone away')
            ) {
                return true;
            }

            if ($throwable->getPrevious() === null) {
                return false;
            }

            $throwable = $throwable->getPrevious();
        }
    }
}
