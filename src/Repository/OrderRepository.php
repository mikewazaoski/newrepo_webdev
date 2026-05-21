<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Get daily sales data for the last 7 days
     */
    public function getDailySalesLast7Days(): array
    {
        $endDate = new \DateTime();
        $startDate = new \DateTime('-6 days');

        $results = $this->createQueryBuilder('o')
            ->select('o.orderDate, SUM(o.quantity * o.price) as total')
            ->where('o.orderDate >= :startDate')
            ->andWhere('o.orderDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('o.orderDate')
            ->orderBy('o.orderDate', 'ASC')
            ->getQuery()
            ->getResult();

        // Process results to group by date
        $dailySales = [];
        foreach ($results as $result) {
            $date = $result['orderDate']->format('Y-m-d');
            $dailySales[$date] = $result['total'];
        }

        return $dailySales;
    }

    /**
     * Get monthly revenue for the last 6 months
     */
    public function getMonthlyRevenueLast6Months(): array
    {
        $endDate = new \DateTime();
        $startDate = new \DateTime('-5 months');

        $results = $this->createQueryBuilder('o')
            ->select('o.orderDate, SUM(o.quantity * o.price) as total')
            ->where('o.orderDate >= :startDate')
            ->andWhere('o.orderDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('o.orderDate')
            ->orderBy('o.orderDate', 'ASC')
            ->getQuery()
            ->getResult();

        // Process results to group by month
        $monthlyRevenue = [];
        foreach ($results as $result) {
            $year = $result['orderDate']->format('Y');
            $month = $result['orderDate']->format('m');
            $key = $year . '-' . $month;

            if (!isset($monthlyRevenue[$key])) {
                $monthlyRevenue[$key] = [
                    'year' => (int)$year,
                    'month' => (int)$month,
                    'total' => 0
                ];
            }
            $monthlyRevenue[$key]['total'] += $result['total'];
        }

        return array_values($monthlyRevenue);
    }

    /**
     * Get daily sales data for the last 7 days for a specific user
     */
    public function getDailySalesLast7DaysForUser($user): array
    {
        $endDate = new \DateTime();
        $startDate = new \DateTime('-6 days');

        $results = $this->createQueryBuilder('o')
            ->select('o.orderDate, SUM(o.quantity * o.price) as total')
            ->where('o.orderDate >= :startDate')
            ->andWhere('o.orderDate <= :endDate')
            ->andWhere('o.createdBy = :user')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('user', $user)
            ->groupBy('o.orderDate')
            ->orderBy('o.orderDate', 'ASC')
            ->getQuery()
            ->getResult();

        // Process results to group by date
        $dailySales = [];
        foreach ($results as $result) {
            $date = $result['orderDate']->format('Y-m-d');
            $dailySales[$date] = $result['total'];
        }

        return $dailySales;
    }

    /**
     * Get monthly revenue for the last 6 months for a specific user
     */
    public function getMonthlyRevenueLast6MonthsForUser($user): array
    {
        $endDate = new \DateTime();
        $startDate = new \DateTime('-5 months');

        $results = $this->createQueryBuilder('o')
            ->select('o.orderDate, SUM(o.quantity * o.price) as total')
            ->where('o.orderDate >= :startDate')
            ->andWhere('o.orderDate <= :endDate')
            ->andWhere('o.createdBy = :user')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('user', $user)
            ->groupBy('o.orderDate')
            ->orderBy('o.orderDate', 'ASC')
            ->getQuery()
            ->getResult();

        // Process results to group by month
        $monthlyRevenue = [];
        foreach ($results as $result) {
            $year = $result['orderDate']->format('Y');
            $month = $result['orderDate']->format('m');
            $key = $year . '-' . $month;

            if (!isset($monthlyRevenue[$key])) {
                $monthlyRevenue[$key] = [
                    'year' => (int)$year,
                    'month' => (int)$month,
                    'total' => 0
                ];
            }
            $monthlyRevenue[$key]['total'] += $result['total'];
        }

        return array_values($monthlyRevenue);
    }

    //    /**
    //     * @return Order[] Returns an array of Order objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
