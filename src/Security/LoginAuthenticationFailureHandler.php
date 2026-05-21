<?php

namespace App\Security;

use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Avoid HTTP 500 on login when the database is unreachable — show the form again with a message.
 */
class LoginAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        if ($request->hasSession()) {
            $message = 'Invalid email or password.';
            if ($this->isDatabaseFailure($exception)) {
                $message = 'Cannot reach the database. In Railway, set DATABASE_URL to ${{MySQL.MYSQL_URL}} on the app service.';
            }
            $request->getSession()->getFlashBag()->add('error', $message);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function isDatabaseFailure(\Throwable $throwable): bool
    {
        while (true) {
            if ($throwable instanceof DBALException) {
                return true;
            }
            $message = strtolower($throwable->getMessage());
            if (str_contains($message, 'connection') || str_contains($message, 'sqlstate')) {
                return true;
            }
            if ($throwable->getPrevious() === null) {
                return false;
            }
            $throwable = $throwable->getPrevious();
        }
    }
}
