<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiMobileController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private ApiTokenService $apiTokenService,
    ) {}

    #[Route('/api/mobile/health', name: 'api_mobile_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1');
            $productCount = $this->productRepository->count([]);
            $categoryCount = $this->categoryRepository->count([]);
            $userCount = $this->userRepository->count([]);

            return new JsonResponse([
                'status' => 'success',
                'database' => 'connected',
                'counts' => [
                    'products' => $productCount,
                    'categories' => $categoryCount,
                    'users' => $userCount,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'database' => 'disconnected',
                'message' => 'Database connection failed',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    #[Route('/api/mobile/profile', name: 'api_mobile_profile', methods: ['GET'])]
    public function profile(Request $request): JsonResponse
    {
        $token = $this->extractBearerToken($request);
        if (!$token) {
            return new JsonResponse(['status' => 'error', 'message' => 'Authentication token required'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->apiTokenService->decodeToken($token);
        if (!$payload) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid or expired token'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->userRepository->find($payload['user_id']);
        if (!$user) {
            return new JsonResponse(['status' => 'error', 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'status' => 'success',
            'data' => $this->apiTokenService->serializeUser($user),
        ], Response::HTTP_OK);
    }

    #[Route('/api/mobile/products', name: 'api_mobile_products', methods: ['GET'])]
    public function getProducts(): JsonResponse
    {
        $products = $this->productRepository->findBy([], ['name' => 'ASC']);

        $data = array_map(function ($product) {
            $category = $product->getCategory();

            return [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'description' => $product->getDescription(),
                'image' => $product->getImage(),
                'stock' => $product->getStock(),
                'category' => $category ? $category->getName() : null,
                'category_id' => $category ? $category->getId() : null,
            ];
        }, $products);

        return new JsonResponse([
            'status' => 'success',
            'data' => $data,
            'count' => count($data),
        ], Response::HTTP_OK);
    }

    #[Route('/api/mobile/categories', name: 'api_mobile_categories', methods: ['GET'])]
    public function getCategories(): JsonResponse
    {
        $categories = $this->categoryRepository->findBy([], ['name' => 'ASC']);

        $data = array_map(function ($category) {
            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'product_count' => $category->getProducts()->count(),
            ];
        }, $categories);

        return new JsonResponse([
            'status' => 'success',
            'data' => $data,
            'count' => count($data),
        ], Response::HTTP_OK);
    }

    #[Route('/api/mobile/customer', name: 'api_mobile_customer', methods: ['GET', 'POST'])]
    public function submitCustomer(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            $data = [
                'name' => $request->query->get('name'),
                'email' => $request->query->get('email'),
                'message' => $request->query->get('message'),
            ];
        } else {
            $data = json_decode($request->getContent(), true);
        }

        if (!isset($data['name']) || !isset($data['email']) || !isset($data['message'])) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Name, email, and message are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty(trim($data['name'])) || empty(trim($data['email'])) || empty(trim($data['message']))) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'All fields must be filled',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Please enter a valid email address',
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Thanks! We received your message and will reply within 24 hours on business days.',
        ], Response::HTTP_OK);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
