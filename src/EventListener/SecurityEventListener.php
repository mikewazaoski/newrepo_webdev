<?php

namespace App\EventListener;

use App\Service\ActivityLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class SecurityEventListener implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogService $logService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        try {
            $user = $event->getAuthenticationToken()->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->logService->logLogin($user);
            }
        } catch (\Throwable) {
            // Do not break login if audit logging fails
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if ($user instanceof \App\Entity\User) {
            $this->logService->logLogout($user);
        }
    }
}

