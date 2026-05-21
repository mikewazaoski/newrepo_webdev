<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
   public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
    ): Response {
        try {
            return $this->handleRegister($request, $userPasswordHasher, $entityManager, $emailVerificationService);
        } catch (\Throwable $e) {
            return $this->render('registration/register.html.twig', [
                'registrationForm' => $this->createForm(RegistrationFormType::class, new User()),
                'registrationError' => $this->isDatabaseConnectionFailure($e)
                    ? 'Account creation failed: the app cannot reach the database. In Railway, add MySQL and set DATABASE_URL to ${{MySQL.MYSQL_URL}} on the app service, then redeploy.'
                    : 'Registration failed. Please try again.',
            ]);
        }
    }

    private function handleRegister(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));


            // Generate verification token
            $verificationToken = $emailVerificationService->generateVerificationToken();
            $user->setVerificationToken($verificationToken);
            $user->setIsVerified(false);

            try {
                $entityManager->persist($user);
                $entityManager->flush();
            } catch (\Throwable $e) {
                $message = $this->isDatabaseConnectionFailure($e)
                    ? 'Account creation failed: the app cannot reach the database. In Railway, add MySQL and set DATABASE_URL to ${{MySQL.MYSQL_URL}} on the app service, then redeploy.'
                    : 'Account creation failed. That email or username may already be in use.';

                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                    'registrationError' => $message,
                ]);
            }

            $verificationUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            try {
                $emailVerificationService->sendVerificationEmail($user, $verificationUrl);
            } catch (\Throwable) {
                // Account is saved; email is optional when MAILER_DSN is null://null
            }

            $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    private function isDatabaseConnectionFailure(\Throwable $throwable): bool
    {
        while (true) {
            if ($throwable instanceof DBALException\ConnectionException) {
                return true;
            }
            if ($throwable instanceof DBALException) {
                $sqlState = $throwable->getSQLState();
                if (in_array($sqlState, ['HY000', '08006', '08001', '08004', '08S01', '2002', '2006'], true)) {
                    return true;
                }
            }
            $message = strtolower($throwable->getMessage());
            if (
                str_contains($message, 'connection refused')
                || str_contains($message, 'connection timed out')
                || str_contains($message, 'getaddrinfo')
                || str_contains($message, 'access denied for user')
                || str_contains($message, 'unknown database')
                || str_contains($message, 'server has gone away')
                || str_contains($message, 'no such file or directory')
            ) {
                return true;
            }
            if ($throwable->getPrevious() === null) {
                return false;
            }
            $throwable = $throwable->getPrevious();
        }
    }
}

