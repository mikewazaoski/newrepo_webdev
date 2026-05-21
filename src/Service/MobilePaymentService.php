<?php

namespace App\Service;

use App\Entity\Order;

class MobilePaymentService
{
    public const METHOD_COD = 'cod';

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';

    /**
     * @return array{method: string, status: string, reference: ?string, paidAt: ?\DateTime}
     */
    public function resolvePayment(string $method, float $totalAmount): array
    {
        if (strtolower(trim($method)) !== self::METHOD_COD) {
            throw new \InvalidArgumentException('Only cash on delivery is accepted.');
        }

        if ($totalAmount <= 0) {
            throw new \InvalidArgumentException('Order total must be greater than zero.');
        }

        return [
            'method' => self::METHOD_COD,
            'status' => self::STATUS_UNPAID,
            'reference' => 'COD',
            'paidAt' => null,
        ];
    }

    public function applyPaymentToOrder(Order $order, array $payment): void
    {
        $order->setPaymentMethod($payment['method']);
        $order->setPaymentStatus($payment['status']);
        $order->setPaymentReference($payment['reference']);
        $order->setPaidAt($payment['paidAt']);
    }

    public static function methodLabel(?string $method): string
    {
        return 'Cash on delivery';
    }
}
