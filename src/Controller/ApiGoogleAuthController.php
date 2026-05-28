<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use App\Service\EmailVerificationService;
use App\Service\MobileCustomerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiGoogleAuthController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerificationService $emailVerificationService,
        private UrlGeneratorInterface $urlGenerator,
        private ValidatorInterface $validator,
        private ApiTokenService $apiTokenService,
        private MobileCustomerService $mobileCustomerService,
    ) {}

    #[Route('/api/mobile/google-auth', name: 'api_mobile_google_auth', methods: ['POST'])]
    public function googleAuth(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $name = isset($data['name']) ? trim((string) $data['name']) : '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'A valid email from Google is required'], Response::HTTP_BAD_REQUEST);
        }

        if ($name === '') {
            $name = strstr($email, '@', true) ?: 'Google User';
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        $isNewAccount = false;

        if (!$user instanceof User) {
            $isNewAccount = true;
            $user = new User();
            $user->setEmail($email);
            $user->setName($name);
            $user->setUsername($this->uniqueUsernameFromEmail($email));
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $user->setRoles(['ROLE_USER']);
            $user->setIsVerified(false);
            $user->setVerificationToken($this->emailVerificationService->generateVerificationToken());

            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $messages = [];
                foreach ($errors as $error) {
                    $messages[] = $error->getMessage();
                }

                return new JsonResponse(['error' => $messages], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } elseif ($name !== '' && ($user->getName() === null || $user->getName() === '')) {
            $user->setName($name);
            $this->entityManager->flush();
        }

        if (!$user->isVerified()) {
            $this->sendVerificationEmail($user);

            return new JsonResponse([
                'requiresVerification' => true,
                'isNewAccount' => $isNewAccount,
                'message' => $isNewAccount
                    ? 'Welcome! We created your account. Please verify your email before signing in.'
                    : 'Please verify your email before signing in with Google.',
                'email' => $user->getEmail(),
                'user' => $this->apiTokenService->serializeUser($user),
            ], Response::HTTP_OK);
        }

        $customer = $this->mobileCustomerService->getOrCreateForUser($user);

        return new JsonResponse([
            'status' => 'success',
            'requiresVerification' => false,
            'isNewAccount' => $isNewAccount,
            'message' => 'Signed in with Google',
            'token' => $this->apiTokenService->generateToken($user),
            'user' => $this->apiTokenService->serializeUser($user),
            'customer' => $this->mobileCustomerService->serialize($customer),
        ], Response::HTTP_OK);
    }

    private function sendVerificationEmail(User $user): void
    {
        if (!$user->getVerificationToken()) {
            $user->setVerificationToken($this->emailVerificationService->generateVerificationToken());
            $this->entityManager->flush();
        }

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $user->getVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
    }

    private function uniqueUsernameFromEmail(string $email): string
    {
        $local = strstr($email, '@', true) ?: 'user';
        $base = preg_replace('/[^a-zA-Z0-9_]/', '_', $local) ?? 'user';
        if ($base === '' || $base === '_') {
            $base = 'user';
        }
        $base = substr($base, 0, 180);
        $candidate = $base;

        for ($i = 0; $i < 64; ++$i) {
            if (null === $this->userRepository->findOneBy(['username' => $candidate])) {
                return $candidate;
            }
            $suffix = '_' . bin2hex(random_bytes(3));
            $candidate = substr($base, 0, max(1, 180 - \strlen($suffix))) . $suffix;
        }

        return 'user_' . bin2hex(random_bytes(12));
    }
}
