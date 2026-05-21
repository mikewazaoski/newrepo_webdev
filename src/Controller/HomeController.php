<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/')]
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        try {
            $user = $this->getUser();
        } catch (\Throwable) {
            $user = null;
        }

        if ($user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $this->redirectToRoute('app_admin_dashboard');
            }

            return $this->redirectToRoute('app_staff_home');
        }
        
        return $this->render('home/index.html.twig');
    }
}
