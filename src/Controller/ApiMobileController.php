<?php

namespace App\Controller;

use App\Controller\Trait\ResolvesMobileUserTrait;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use App\Service\MobileCustomerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiMobileController extends AbstractController
{
    use ResolvesMobileUserTrait;

    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private ApiTokenService $apiTokenService,
        private MobileCustomerService $mobileCustomerService,
    ) {
    }

    #[Route('/api/mobile/health', name: 'api_mobile_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $dbUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL');
        $dbUrl = is_string($dbUrl) ? $dbUrl : '';
        $diagnostics = [
            'database_url_set' => $dbUrl !== '',
            'database_host' => $dbUrl !== '' ? (parse_url($dbUrl, PHP_URL_HOST) ?: 'unknown') : null,
            'env_local_php' => is_file($this->getParameter('kernel.project_dir').'/.env.local.php'),
        ];

        if ($dbUrl === '') {
            return new JsonResponse([
                'status' => 'error',
                'database' => 'disconnected',
                'message' => 'DATABASE_URL is not configured',
                'hint' => 'In Railway: add MySQL, then on the app service set DATABASE_URL=${{MySQL.MYSQL_URL}} and redeploy.',
                'diagnostics' => $diagnostics,
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

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
                'hint' => 'Check Railway deploy logs for "Database OK". Ensure MySQL is in the same project and DATABASE_URL=${{MySQL.MYSQL_URL}} is on the app service.',
                'error' => $e->getMessage(),
                'diagnostics' => $diagnostics,
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    #[Route('/api/mobile/profile', name: 'api_mobile_profile', methods: ['GET'])]
    public function profile(Request $request): JsonResponse
    {
        $user = $this->resolveMobileUser($request, $this->apiTokenService, $this->userRepository);
        if (!$user instanceof User) {
            return $user;
        }

        $customer = $this->mobileCustomerService->findForUser($user);
        $customerData = $customer ? $this->mobileCustomerService->serialize($customer) : null;

        return $this->mobileJsonSuccess([
            'data' => [
                'user' => $this->apiTokenService->serializeUser($user),
                'customer' => $customerData,
            ],
        ]);
    }

    #[Route('/api/mobile/products', name: 'api_mobile_products', methods: ['GET'])]
    public function getProducts(Request $request): JsonResponse
    {
        $products = $this->productRepository->findBy([], ['name' => 'ASC']);
        $baseUrl = $request->getSchemeAndHttpHost();

        $data = array_map(function ($product) use ($baseUrl) {
            $category = $product->getCategory();
            $image = $product->getImage();

            return [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'description' => $product->getDescription(),
                'image' => $image,
                'imageUrl' => $image ? $baseUrl . '/uploads/images/' . $image : null,
                'stock' => $product->getStock(),
                'category' => $category ? $category->getName() : null,
                'category_id' => $category ? $category->getId() : null,
            ];
        }, $products);

        return $this->mobileJsonSuccess([
            'data' => $data,
            'count' => count($data),
        ]);
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

        return $this->mobileJsonSuccess([
            'data' => $data,
            'count' => count($data),
        ]);
    }

    /** Public contact form (not customer CRUD). */
    #[Route('/api/mobile/contact', name: 'api_mobile_contact', methods: ['POST'])]
    public function contact(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->mobileJsonError('Invalid JSON body');
        }

        if (!isset($data['name'], $data['email'], $data['message'])) {
            return $this->mobileJsonError('Name, email, and message are required');
        }

        if (empty(trim((string) $data['name'])) || empty(trim((string) $data['email'])) || empty(trim((string) $data['message']))) {
            return $this->mobileJsonError('All fields must be filled');
        }

        if (!filter_var(trim((string) $data['email']), FILTER_VALIDATE_EMAIL)) {
            return $this->mobileJsonError('Please enter a valid email address');
        }

        return $this->mobileJsonSuccess([
            'message' => 'Thanks! We received your message and will reply within 24 hours on business days.',
        ]);
    }
}
