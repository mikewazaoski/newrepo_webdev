<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Product;
use App\Repository\ActivityLogRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(
        EntityManagerInterface $em,
        UserRepository $userRepository,
        ActivityLogRepository $logRepository,
        OrderRepository $orderRepository,
    ): Response {
        $metrics = $this->buildDashboardMetrics($em, $userRepository, $logRepository, $orderRepository);

        return $this->render('admin/dashboard.html.twig', $metrics);
    }

    #[Route('/dashboard/updates', name: 'app_admin_dashboard_updates', methods: ['GET'])]
    public function dashboardUpdates(
        EntityManagerInterface $em,
        UserRepository $userRepository,
        ActivityLogRepository $logRepository,
        OrderRepository $orderRepository,
    ): JsonResponse {
        $metrics = $this->buildDashboardMetrics($em, $userRepository, $logRepository, $orderRepository);

        return new JsonResponse([
            'total_products' => $metrics['total_products'],
            'total_orders' => $metrics['total_orders'],
            'total_customers' => $metrics['total_customers'],
            'total_revenue' => $metrics['total_revenue'],
            'latest_order_id' => $metrics['latest_order_id'],
            'latest_customer_id' => $metrics['latest_customer_id'],
            'daily_sales' => $metrics['daily_sales'],
            'monthly_revenue' => $metrics['monthly_revenue'],
            'category_distribution' => array_map(
                static fn (array $row) => [
                    'category' => $row['category'] ?? 'Uncategorized',
                    'count' => (int) ($row['count'] ?? 0),
                ],
                $metrics['category_distribution']
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardMetrics(
        EntityManagerInterface $em,
        UserRepository $userRepository,
        ActivityLogRepository $logRepository,
        OrderRepository $orderRepository,
    ): array {
        $totalUsers = $userRepository->count([]);
        $totalStaff = $userRepository->countByRole('ROLE_STAFF');
        $totalProducts = $em->getRepository(Product::class)->count([]);
        $totalCategories = $em->getRepository(Category::class)->count([]);
        $totalOrders = $em->getRepository(Order::class)->count([]);
        $totalCustomers = $em->getRepository(Customer::class)->count([]);
        $totalRecords = $totalProducts + $totalCategories + $totalOrders + $totalCustomers;

        $totalRevenue = $em->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->select('SUM(o.quantity * o.price)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $latestOrder = $orderRepository->findOneBy([], ['id' => 'DESC']);
        $latestCustomer = $em->getRepository(Customer::class)->findOneBy([], ['id' => 'DESC']);

        $dailySales = $orderRepository->getDailySalesLast7Days();
        $monthlyRevenue = $orderRepository->getMonthlyRevenueLast6Months();
        $categoryDistribution = $em->getRepository(Product::class)->getCategoryDistribution();
        $recentActivities = $logRepository->findRecent(10);

        return [
            'total_users' => $totalUsers,
            'total_staff' => $totalStaff,
            'total_records' => $totalRecords,
            'total_products' => $totalProducts,
            'total_categories' => $totalCategories,
            'total_orders' => $totalOrders,
            'total_customers' => $totalCustomers,
            'total_revenue' => (float) $totalRevenue,
            'latest_order_id' => $latestOrder?->getId() ?? 0,
            'latest_customer_id' => $latestCustomer?->getId() ?? 0,
            'daily_sales' => $dailySales,
            'monthly_revenue' => $monthlyRevenue,
            'category_distribution' => $categoryDistribution,
            'recent_activities' => $recentActivities,
        ];
    }
}
