<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\RequestStack;

#[Route('/stock')]
final class StockManagementController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    #[Route('/', name: 'app_stock_management', methods: ['GET'])]
    public function index(): Response
    {
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access Denied: Staff or Admin role required.');
        }

        $products = $this->productRepository->findBy([], ['name' => 'ASC']);

        return $this->render('stock_management/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/update/{id}', name: 'app_stock_update', methods: ['POST'])]
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'message' => 'Access Denied: Staff or Admin role required.'], 403);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        // Validate CSRF token
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('stock_update' . $product->getId(), $submittedToken)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $newStock = (int) $request->request->get('stock');
        $oldStock = $product->getStock();
        
        if ($newStock < 0) {
            return new JsonResponse(['success' => false, 'message' => 'Stock cannot be negative'], 400);
        }

        $product->setStock($newStock);
        
        try {
            $this->entityManager->flush();
            
            // Temporarily disable activity logging completely
            // try {
            //     $this->activityLogService->logStockChange(
            //         $user,
            //         $product,
            //         $oldStock,
            //         $newStock
            //     );
            // } catch (\Exception $e) {
            //     // Log the error but don't fail the operation
            //     error_log('Activity logging failed: ' . $e->getMessage());
            // }

            return new JsonResponse([
                'success' => true,
                'message' => 'Stock updated successfully',
                'oldStock' => $oldStock,
                'newStock' => $newStock,
                'productName' => $product->getName()
            ]);
        } catch (\Exception $e) {
            // Handle database errors
            error_log('Database error: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to update stock: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/restock/{id}', name: 'app_stock_restock', methods: ['POST'])]
    public function restock(Request $request, Product $product): JsonResponse
    {
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'message' => 'Access Denied: Staff or Admin role required.'], 403);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        // Validate CSRF token
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('stock_restock' . $product->getId(), $submittedToken)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $restockAmount = (int) $request->request->get('restockAmount');
        $oldStock = $product->getStock();
        
        if ($restockAmount <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Restock amount must be positive'], 400);
        }

        $newStock = $oldStock + $restockAmount;
        $product->setStock($newStock);
        
        try {
            $this->entityManager->flush();
            
            // Temporarily disable activity logging completely
            // try {
            //     $this->activityLogService->logStockChange(
            //         $user,
            //         $product,
            //         $oldStock,
            //         $newStock,
            //         "Restocked: +{$restockAmount}"
            //     );
            // } catch (\Exception $e) {
            //     // Log the error but don't fail the operation
            //     error_log('Activity logging failed: ' . $e->getMessage());
            // }

            return new JsonResponse([
                'success' => true,
                'message' => 'Product restocked successfully',
                'oldStock' => $oldStock,
                'newStock' => $newStock,
                'restockAmount' => $restockAmount,
                'productName' => $product->getName()
            ]);
        } catch (\Exception $e) {
            // Handle database errors
            error_log('Database error: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to restock product: ' . $e->getMessage()
            ], 500);
        }
    }
}
