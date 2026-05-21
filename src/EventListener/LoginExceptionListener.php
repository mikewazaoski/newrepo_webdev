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
 * Show a clear login error instead of HTTP 500 when the database is unreachable.
 */
class LoginExceptionListener implements EventSubscriberInterface
{
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
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->isLoginRequest($request)) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!$this->isDatabaseFailure($throwable)) {
            return;
        }

        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add(
                'error',
                'Sign-in is unavailable because the database is not connected. In Railway, set DATABASE_URL to ${{MySQL.MYSQL_URL}} and redeploy.'
            );
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_login')
        ));
    }

    private function isLoginRequest(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->attributes->get('_route') === 'app_login';
    }

    private function isDatabaseFailure(\Throwable $throwable): bool
    {
        while (true) {
            if ($throwable instanceof DBALException) {
                return true;
            }
            $message = strtolower($throwable->getMessage());
            if (
                str_contains($message, 'connection')
                || str_contains($message, 'database')
                || str_contains($message, 'sqlstate')
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
