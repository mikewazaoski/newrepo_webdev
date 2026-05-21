<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/category')]
#[IsGranted('ROLE_USER')]
final class CategoryController extends AbstractController
{
    #[Route(name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        // Staff can only see their own records, admins see all
        if ($this->isGranted('ROLE_ADMIN')) {
            $categories = $categoryRepository->findAll();
        } else {
            $categories = $categoryRepository->findBy(['createdBy' => $this->getUser()]);
        }

        return $this->render('category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogService $logService): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set createdBy for ownership tracking
            $category->setCreatedBy($this->getUser());

            $entityManager->persist($category);
            $entityManager->flush();

            $logService->logCreate($this->getUser(), 'Category', $category->getId(), ['name' => $category->getName()]);

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'])]
    public function show(Category $category): Response
    {
        // Staff can only view their own records
        if (!$this->isGranted('ROLE_ADMIN') && $category->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only view your own records.');
        }

        return $this->render('category/show.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager, ActivityLogService $logService): Response
    {
        // Staff can only edit their own records
        if (!$this->isGranted('ROLE_ADMIN') && $category->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only edit your own records.');
        }

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure category name is set
            $name = $form->get('name')->getData();
            if ($name !== null) {
                $category->setName((string) $name);
            }
            
            $entityManager->persist($category);
            $entityManager->flush();

            $logService->logUpdate($this->getUser(), 'Category', $category->getId(), ['name' => $category->getName()]);

            $this->addFlash('success', 'Category updated successfully!');
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager, ActivityLogService $logService): Response
    {
        // Staff can only delete their own records
        if (!$this->isGranted('ROLE_ADMIN') && $category->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only delete your own records.');
        }

        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->getPayload()->getString('_token'))) {
            $categoryId = $category->getId();
            $categoryData = ['name' => $category->getName()];
            $entityManager->remove($category);
            $entityManager->flush();
            $logService->logDelete($this->getUser(), 'Category', $categoryId, $categoryData);
        }

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }
}
