<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/history')]
final class OurHistoryController extends AbstractController
{
    #[Route('/', name: 'app_our_history', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('history/index.html.twig');
    }
}
