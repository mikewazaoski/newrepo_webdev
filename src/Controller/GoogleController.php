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
    public function connect(): Response
    {
        if (!$this->isGoogleOAuthConfigured()) {
            $this->addFlash('error', 'Google sign-in is not configured on this server yet.');

            return $this->redirectToRoute('app_login');
        }

        return $this->clientRegistry->getClient('google')->redirect();
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(): Response
    {
        throw new \LogicException('Google authentication is handled by GoogleAuthenticator.');
    }

    private function isGoogleOAuthConfigured(): bool
    {
        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? $_SERVER['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID');
        $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? $_SERVER['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET');

        return is_string($clientId) && $clientId !== ''
            && is_string($clientSecret) && $clientSecret !== '';
    }
}
