<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use App\Service\EmailVerificationService;
use App\Service\MobileCustomerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ApiEmailVerificationController extends AbstractController
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private ApiTokenService $apiTokenService,
        private MobileCustomerService $mobileCustomerService,
    ) {}

    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['token']) || empty($data['token'])) {
            return new JsonResponse(['error' => 'Verification token is required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->emailVerificationService->verifyToken($data['token']);

        if (!$user) {
            return new JsonResponse(['error' => 'Invalid or expired verification token'], Response::HTTP_BAD_REQUEST);
        }

        $customer = $this->mobileCustomerService->getOrCreateForUser($user);

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Email verified successfully. You can now use the app.',
            'token' => $this->apiTokenService->generateToken($user),
            'user' => $this->apiTokenService->serializeUser($user),
            'customer' => $this->mobileCustomerService->serialize($customer),
        ], Response::HTTP_OK);
    }

    #[Route('/api/resend-verification', name: 'api_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || empty($data['email'])) {
            return new JsonResponse(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if ($user->isVerified()) {
            return new JsonResponse(['error' => 'Email is already verified'], Response::HTTP_BAD_REQUEST);
        }

        // Generate new token
        $verificationToken = $this->emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($verificationToken);

        // Persist the new token
        $this->entityManager->flush();

        // Generate verification URL (points to web endpoint)
        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $verificationToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Send verification email
        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);

        return new JsonResponse(['success' => true, 'message' => 'Verification email sent'], Response::HTTP_OK);
    }
}