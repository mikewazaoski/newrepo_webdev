<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OrderFixtures extends Fixture implements DependentFixtureInterface
{
    public const REF_ALICE_DOG_FOOD = 'order.alice-dog-food';
    public const REF_BOB_CAT_LITTER = 'order.bob-cat-litter';
    public const REF_CAROL_FISH_FLAKES = 'order.carol-fish-flakes';
    public const REF_ALICE_BIRD_SEED = 'order.alice-bird-seed';

    public function getDependencies(): array
    {
        return [
            CustomerFixtures::class,
            ProductFixtures::class,
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $staff = $this->getReference(UserFixtures::REF_STAFF, User::class);

        $orders = [
            [
                self::REF_ALICE_DOG_FOOD,
                CustomerFixtures::REF_ALICE,
                ProductFixtures::REF_PREMIUM_DOG_KIBBLE,
                2,
                'pending',
                'unpaid',
                null,
                'fixture-order-001',
            ],
            [
                self::REF_BOB_CAT_LITTER,
                CustomerFixtures::REF_BOB,
                ProductFixtures::REF_CAT_LITTER,
                1,
                'processing',
                'paid',
                'card',
                'fixture-order-002',
            ],
            [
                self::REF_CAROL_FISH_FLAKES,
                CustomerFixtures::REF_CAROL,
                ProductFixtures::REF_FISH_FLAKES,
                3,
                'delivered',
                'paid',
                'cash',
                'fixture-order-003',
            ],
            [
                self::REF_ALICE_BIRD_SEED,
                CustomerFixtures::REF_ALICE,
                ProductFixtures::REF_BIRD_SEED_MIX,
                1,
                'cancelled',
                'unpaid',
                null,
                'fixture-order-004',
            ],
        ];

        foreach ($orders as [$ref, $customerRef, $productRef, $quantity, $status, $paymentStatus, $paymentMethod, $orderRef]) {
            /** @var Product $product */
            $product = $this->getReference($productRef, Product::class);
            $lineTotal = $product->getPrice() * $quantity;

            $order = new Order();
            $order->setCustomer($this->getReference($customerRef, Customer::class));
            $order->setProductName($product->getName());
            $order->setProductId($product->getId());
            $order->setQuantity((float) $quantity);
            $order->setPrice($lineTotal);
            $order->setStatus($status);
            $order->setPaymentStatus($paymentStatus);
            $order->setPaymentMethod($paymentMethod);
            $order->setOrderRef($orderRef);
            $order->setCreatedBy($staff);
            $order->setOrderDate(new \DateTime('-' . random_int(1, 14) . ' days'));

            if ($paymentStatus === 'paid') {
                $order->setPaidAt(new \DateTime('-' . random_int(0, 7) . ' days'));
                $order->setPaymentReference('PAY-' . strtoupper(substr($orderRef, -3)));
            }

            $manager->persist($order);
            $this->addReference($ref, $order);
        }

        $manager->flush();
    }
}
