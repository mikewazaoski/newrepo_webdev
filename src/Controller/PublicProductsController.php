<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products')]
final class PublicProductsController extends AbstractController
{
    #[Route('/', name: 'app_products', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ): Response {
        $search = trim((string) $request->query->get('search', ''));
        $categoryIdParam = $request->query->get('category', '');
        $categoryId = ($categoryIdParam !== '' && is_numeric($categoryIdParam)) ? (int) $categoryIdParam : null;
        if ($categoryId !== null && $categoryId <= 0) {
            $categoryId = null;
        }

        $products = $productRepository->searchAndFilter(
            $search !== '' ? $search : null,
            $categoryId
        );

        return $this->render('products/index.html.twig', [
            'products' => $products,
            'search' => $search,
            'category_id' => $categoryId,
            'categories' => $categoryRepository->findAll(),
        ]);
    }
}

