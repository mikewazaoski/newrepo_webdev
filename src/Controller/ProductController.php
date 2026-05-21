<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/product')]
#[IsGranted('ROLE_USER')]
final class ProductController extends AbstractController
{
    #[Route('/', name: 'app_product_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        // Both admin and staff can see all products
        // Staff can edit/delete/restock any product (no restrictions)
        
        // Search and filter functionality
        $search = $request->query->get('search', '');
        
        // Safely get category ID - handle empty/invalid values
        $categoryIdParam = $request->query->get('category', '');
        $categoryId = ($categoryIdParam !== '' && is_numeric($categoryIdParam)) ? (int)$categoryIdParam : 0;
        
        if ($search || $categoryId) {
            $products = $productRepository->searchAndFilter($search, $categoryId > 0 ? $categoryId : null);
        } else {
            $products = $productRepository->findAll();
        }

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'search' => $search,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, ActivityLogService $logService): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Price is already handled by the form (NumberType returns numeric value)
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $product->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading image: ' . $e->getMessage());
                }
            }

            // Set createdBy for ownership tracking
            $product->setCreatedBy($this->getUser());

            $entityManager->persist($product);
            $entityManager->flush();

            // Log activity - ensure all values are strings
            $productName = $product->getName() ?? 'Unknown';
            $productPrice = $product->getPrice() !== null ? (string) number_format($product->getPrice(), 2) : '0.00';
            
            $logService->logCreate(
                $this->getUser(),
                'Product',
                $product->getId(),
                [
                    'name' => $productName,
                    'price' => $productPrice
                ]
            );

            $this->addFlash('success', 'Product added successfully!');
            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/new.html.twig', [
            'form' => $form,
            'product' => $product,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        // Both admin and staff can view all products
        // Staff can edit/delete/restock any product (no restrictions)
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager, SluggerInterface $slugger, ActivityLogService $logService): Response
    {
        // Admin can edit any product
        // Staff can edit any product including those created by admin
        // No restrictions needed for staff editing

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure all fields are properly set
            $name = $form->get('name')->getData();
            $price = $form->get('price')->getData();
            $description = $form->get('description')->getData();
            $category = $form->get('category')->getData();
            
            // Set fields explicitly to ensure they're saved
            if ($name !== null) {
                $product->setName((string) $name);
            }
            if ($price !== null) {
                $product->setPrice((float) $price);
            }
            if ($description !== null) {
                $product->setDescription((string) $description);
            }
            $product->setCategory($category);
            
            // Handle image upload
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    $product->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading image: ' . $e->getMessage());
                }
            }

            $entityManager->persist($product);
            $entityManager->flush();

            // Log activity - ensure all values are strings
            $productName = $product->getName() ?? 'Unknown';
            $productPrice = $product->getPrice() !== null ? (string) number_format($product->getPrice(), 2) : '0.00';
            
            $logService->logUpdate(
                $this->getUser(),
                'Product',
                $product->getId(),
                [
                    'name' => $productName,
                    'price' => $productPrice
                ]
            );

            $this->addFlash('success', 'Product updated successfully!');
            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'form' => $form,
            'product' => $product,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager, ActivityLogService $logService): Response
    {
        // Admin can delete any product
        // Staff can delete any product including those created by admin
        // No restrictions needed for staff deleting

        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->getPayload()->getString('_token'))) {
            $productId = $product->getId();
            $productData = ['name' => $product->getName(), 'price' => $product->getPrice()];
            
            $entityManager->remove($product);
            $entityManager->flush();

            // Log activity
            $logService->logDelete($this->getUser(), 'Product', $productId, $productData);

            $this->addFlash('success', 'Product deleted successfully!');
        }

        return $this->redirectToRoute('app_product_index');
    }

    #[Route('/{id}/restock', name: 'app_product_restock', methods: ['GET', 'POST'])]
    public function restock(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager,
        ActivityLogService $logService
    ): Response {
        // Staff can restock any product including those created by admin
        // No restrictions needed for staff restocking

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token', '');
            $amount = (int) $request->request->get('amount', 0);

            if (!$this->isCsrfTokenValid('restock' . $product->getId(), $token)) {
                $this->addFlash('error', 'Invalid CSRF token. Please try again.');
            } elseif ($amount <= 0) {
                $this->addFlash('error', 'Restock amount must be greater than 0.');
            } else {
                $before = $product->getStock();
                $product->setStock($before + $amount);
                $entityManager->flush();

                $logService->logUpdate($this->getUser(), 'Product', $product->getId(), [
                    'name' => $product->getName(),
                    'stock_change' => '+' . (string) $amount,
                    'stock_before' => (string) $before,
                    'stock_after' => (string) $product->getStock(),
                ]);

                $this->addFlash('success', 'Stock restocked successfully!');
                return $this->redirectToRoute('app_product_show', ['id' => $product->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('product/restock.html.twig', [
            'product' => $product,
        ]);
    }
}
