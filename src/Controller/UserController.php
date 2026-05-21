<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Form\UserEditType;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogService $logService,
        UserRepository $userRepository
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get form data
            $name = $form->get('name')->getData();
            $email = $form->get('email')->getData();
            $username = $form->get('username')->getData();
            $plainPassword = $form->get('password')->getData();
            $isActive = $form->get('isActive')->getData();
            
            // Roles are now handled by the data transformer, so user->getRoles() should work
            // But we need to ensure it's set correctly
            $roles = $user->getRoles();
            // Remove ROLE_USER if present (it's auto-added)
            $roles = array_filter($roles, fn($r) => $r !== 'ROLE_USER');
            if (empty($roles)) {
                $user->setRoles(['ROLE_STAFF']);
            }
            
            // Check if email already exists
            $existingUser = $userRepository->findOneBy(['email' => $user->getEmail()]);
            if ($existingUser) {
                $this->addFlash('error', 'An account with this email already exists.');
                return $this->render('user/new.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }

            // Check if username already exists
            $existingUsername = $userRepository->findOneBy(['username' => $user->getUsername()]);
            if ($existingUsername) {
                $this->addFlash('error', 'This username is already taken.');
                return $this->render('user/new.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }

            // Hash password from form
            if ($plainPassword && is_string($plainPassword)) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Ensure createdAt is set
            if (!$user->getCreatedAt()) {
                $user->setCreatedAt(new \DateTime());
            }

            // Ensure isActive is set
            $user->setIsActive($isActive !== null ? (bool) $isActive : true);

            $entityManager->persist($user);
            $entityManager->flush();

            // Log activity - Admin creates a user
            $logService->logCreate(
                $this->getUser(),
                'User',
                $user->getId(),
                [
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'roles' => implode(', ', $user->getRoles())
                ]
            );

            $this->addFlash('success', 'User created successfully.');

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogService $logService
    ): Response {
        $oldData = [
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
        ];

        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if email is being changed and if it already exists
            $newEmail = $form->get('email')->getData();
            if ($newEmail !== $oldData['email']) {
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $newEmail]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('error', 'An account with this email already exists.');
                    return $this->render('user/edit.html.twig', [
                        'user' => $user,
                        'form' => $form,
                    ]);
                }
            }

            // Check if username is being changed and if it already exists
            $newUsername = $form->get('username')->getData();
            if ($newUsername !== $oldData['username']) {
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['username' => $newUsername]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('error', 'This username is already taken.');
                    return $this->render('user/edit.html.twig', [
                        'user' => $user,
                        'form' => $form,
                    ]);
                }
            }

            // Handle password reset if provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword && strlen($plainPassword) >= 6) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Roles are now handled by the data transformer automatically
            // Just ensure roles are set correctly
            $roles = $user->getRoles();
            $roles = array_filter($roles, fn($r) => $r !== 'ROLE_USER');
            if (empty($roles)) {
                $user->setRoles(['ROLE_STAFF']);
            }

            $entityManager->flush();

            // Log activity - convert arrays to strings for logging
            $newData = [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'roles' => implode(', ', $user->getRoles()),
                'isActive' => $user->isActive() ? 'true' : 'false',
            ];
            $oldDataString = [
                'name' => $oldData['name'],
                'email' => $oldData['email'],
                'username' => $oldData['username'],
                'roles' => is_array($oldData['roles']) ? implode(', ', $oldData['roles']) : (string) $oldData['roles'],
                'isActive' => $oldData['isActive'] ? 'true' : 'false',
            ];
            $logService->logUpdate(
                $this->getUser(),
                'User',
                $user->getId(),
                ['old' => $oldDataString, 'new' => $newData]
            );

            $this->addFlash('success', 'User updated successfully.');

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        ActivityLogService $logService
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $userId = $user->getId();
            $userData = [
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => implode(', ', $user->getRoles()),
            ];

            $entityManager->remove($user);
            $entityManager->flush();

            // Log activity - Admin deletes a user
            $logService->logDelete($this->getUser(), 'User', $userId, $userData);

            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'app_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        ActivityLogService $logService
    ): Response {
        if ($this->isCsrfTokenValid('toggle' . $user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(!$user->isActive());
            $entityManager->flush();

            $status = $user->isActive() ? 'enabled' : 'disabled';
            $logService->logUpdate(
                $this->getUser(),
                'User',
                $user->getId(),
                ['action' => 'status_change', 'status' => $status]
            );

            $this->addFlash('success', "User {$status} successfully.");
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}

