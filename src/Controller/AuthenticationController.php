<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthenticationController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        try {
            $currentUser = $this->getUser();
            if ($currentUser) {
                if ($this->isGranted('ROLE_ADMIN')) {
                    return $this->redirectToRoute('app_admin_dashboard');
                }

                return $this->redirectToRoute('app_home');
            }

            return $this->render('authentication/login.html.twig', [
                'last_username' => $authenticationUtils->getLastUsername(),
                'error' => $authenticationUtils->getLastAuthenticationError(),
            ]);
        } catch (\Throwable) {
            return $this->render('authentication/login.html.twig', [
                'last_username' => '',
                'error' => null,
                'loginError' => 'The app cannot reach the database. In Railway, add MySQL and set DATABASE_URL to ${{MySQL.MYSQL_URL}} on the app service, then redeploy.',
            ]);
        }
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
