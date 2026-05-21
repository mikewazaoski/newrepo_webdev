<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'connect_google_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): User {
                $googleUser = $client->fetchUserFromToken($accessToken);
                if (!$googleUser instanceof GoogleUser) {
                    throw new CustomUserMessageAuthenticationException('Invalid response from Google.');
                }

                $googleData = $googleUser->toArray();
                $email = $googleData['email'] ?? null;
                if (!\is_string($email) || $email === '') {
                    throw new CustomUserMessageAuthenticationException('Google did not return an email address for this account.');
                }

                // Extract name from Google data
                $name = $googleData['name'] ?? null;
                if (!\is_string($name) || $name === '') {
                    // Fallback to email prefix if name is not available
                    $name = strstr($email, '@', true) ?: 'Google User';
                }

                $user = $this->userRepository->findOneBy(['email' => $email]);

                if ($user instanceof User) {
                    if (!$this->isStaffPortalUser($user)) {
                        throw new CustomUserMessageAuthenticationException('Google sign-in is only available for staff accounts. Please use email and password to sign in as a customer.');
                    }

                    $this->ensureStaffRoleStored($user);
                    $user->setIsVerified(true);
                    $user->setVerificationToken(null);
                    $this->entityManager->flush();

                    return $user;
                }

                $newUser = new User();
                $newUser->setEmail($email);
                $newUser->setName($name);
                $newUser->setUsername($this->uniqueUsernameFromEmail($email));
                $newUser->setPassword($this->passwordHasher->hashPassword($newUser, bin2hex(random_bytes(32))));
                $newUser->setRoles(['ROLE_USER', 'ROLE_STAFF']);
                $newUser->setIsVerified(true);
                $newUser->setVerificationToken(null);

                $this->entityManager->persist($newUser);
                $this->entityManager->flush();

                return $newUser;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof User) {
            $this->activityLogService->logLogin($user);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_staff_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->hasSession()) {
            $message = $exception->getMessage();
            if ($exception->getPrevious() instanceof \Throwable) {
                $prevMsg = $exception->getPrevious()->getMessage();
                if (str_contains($prevMsg, 'redirect_uri_mismatch')) {
                    $message = 'OAuth redirect URI mismatch. Ensure http://localhost:8000/connect/google/check is configured exactly in Google Cloud Console.';
                }
            }
            $request->getSession()->getFlashBag()->add('oauth_error', $message);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function isStaffPortalUser(User $user): bool
    {
        return $user->isStaff() || $user->isAdmin();
    }

    /**
     * Persist ROLE_STAFF for admins who only had ROLE_ADMIN stored (getRoles still grants access).
     */
    private function ensureStaffRoleStored(User $user): void
    {
        $currentRoles = $user->getRoles();
        if (\in_array('ROLE_ADMIN', $currentRoles, true) && !\in_array('ROLE_STAFF', $currentRoles, true)) {
            $user->addRole('ROLE_STAFF');
            $user->addRole('ROLE_USER');
        }
    }

    private function uniqueUsernameFromEmail(string $email): string
    {
        $local = strstr($email, '@', true) ?: 'staff';
        $base = preg_replace('/[^a-zA-Z0-9_]/', '_', $local) ?? 'staff';
        if ('' === $base || '_' === $base) {
            $base = 'staff';
        }
        $base = substr($base, 0, 180);
        $candidate = $base;
        for ($i = 0; $i < 64; ++$i) {
            $existing = $this->userRepository->findOneBy(['username' => $candidate]);
            if (null === $existing) {
                return $candidate;
            }
            $suffix = '_' . bin2hex(random_bytes(3));
            $candidate = substr($base, 0, max(1, 180 - \strlen($suffix))) . $suffix;
        }

        return 'staff_' . bin2hex(random_bytes(12));
    }
}