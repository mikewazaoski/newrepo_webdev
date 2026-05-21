<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contact')]
final class ContactController extends AbstractController
{
    #[Route('/', name: 'app_contact_us', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('contact_us', $token)) {
                $this->addFlash('error', 'Invalid form submission. Please try again.');
            } else {
                $name = trim((string) $request->request->get('name', ''));
                $email = trim((string) $request->request->get('email', ''));
                $message = trim((string) $request->request->get('message', ''));

                if ($name === '' || $email === '' || $message === '') {
                    $this->addFlash('error', 'Please fill out all fields.');
                } else {
                    // In a real app, you'd send an email here (mailer integration).
                    $this->addFlash('success', 'Thanks for contacting us! We will get back to you soon.');

                    return $this->redirectToRoute('app_contact_us', [], Response::HTTP_SEE_OTHER);
                }
            }
        }

        return $this->render('contact/index.html.twig');
    }
}

