<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GoogleController extends AbstractController
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
    ) {
    }

    #[Route('/connect/google', name: 'connect_google_start')]
    public function connect(): RedirectResponse
    {
        return $this->clientRegistry->getClient('google')->redirect();
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(): Response
    {
        throw new \LogicException('Google authentication is handled by GoogleAuthenticator.');
    }
}