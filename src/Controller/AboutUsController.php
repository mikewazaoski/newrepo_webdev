<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/about')]
final class AboutUsController extends AbstractController
{
    #[Route('/', name: 'app_about_us', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('about/index.html.twig');
    }
}

