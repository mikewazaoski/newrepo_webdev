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
        // Check if user is authenticated and redirect to appropriate dashboard
        if ($this->getUser()) {
            $user = $this->getUser();
            
            // Redirect admins to admin dashboard, others to staff dashboard
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $this->redirectToRoute('app_admin_dashboard');
            }
            
            return $this->redirectToRoute('app_staff_home');
        }
        
        return $this->render('home/index.html.twig');
    }
}
