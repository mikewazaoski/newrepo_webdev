<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        ActivityLogRepository $logRepository
    ): Response {
        // Get totals
        $totalUsers = $userRepository->count([]);
        $totalStaff = $userRepository->countByRole('ROLE_STAFF');
        $totalProducts = $em->getRepository(Product::class)->count([]);
        $totalCategories = $em->getRepository(Category::class)->count([]);
        $totalOrders = $em->getRepository(Order::class)->count([]);
        $totalCustomers = $em->getRepository(Customer::class)->count([]);
        $totalRecords = $totalProducts + $totalCategories + $totalOrders + $totalCustomers;

        // Calculate total revenue
        $totalRevenue = $em->getRepository(Order::class)
            ->createQueryBuilder('o')
            ->select('SUM(o.quantity * o.price)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Get chart data
        $dailySales = $em->getRepository(Order::class)->getDailySalesLast7Days();
        $monthlyRevenue = $em->getRepository(Order::class)->getMonthlyRevenueLast6Months();
        $categoryDistribution = $em->getRepository(Product::class)->getCategoryDistribution();

        // Get recent activities
        $recentActivities = $logRepository->findRecent(10);

        return $this->render('admin/dashboard.html.twig', [
            'total_users' => $totalUsers,
            'total_staff' => $totalStaff,
            'total_records' => $totalRecords,
            'total_products' => $totalProducts,
            'total_categories' => $totalCategories,
            'total_orders' => $totalOrders,
            'total_customers' => $totalCustomers,
            'total_revenue' => $totalRevenue,
            'daily_sales' => $dailySales,
            'monthly_revenue' => $monthlyRevenue,
            'category_distribution' => $categoryDistribution,
            'recent_activities' => $recentActivities,
        ]);
    }
}

