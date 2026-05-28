<?php

namespace App\DataFixtures;

use App\Entity\ActivityLog;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ActivityLogFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CategoryFixtures::class,
            ProductFixtures::class,
            CustomerFixtures::class,
            OrderFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference(UserFixtures::REF_ADMIN, User::class);
        $staff = $this->getReference(UserFixtures::REF_STAFF, User::class);
        $category = $this->getReference(CategoryFixtures::REF_DOG_FOOD, Category::class);
        $product = $this->getReference(ProductFixtures::REF_PREMIUM_DOG_KIBBLE, Product::class);
        $customer = $this->getReference(CustomerFixtures::REF_ALICE, Customer::class);
        $order = $this->getReference(OrderFixtures::REF_ALICE_DOG_FOOD, Order::class);

        $entries = [
            [
                $admin,
                'LOGIN',
                'User',
                $admin->getId(),
                sprintf('User %s logged in', $admin->getUsername()),
                ['target' => sprintf('User: %s (ID: %d)', $admin->getUsername(), $admin->getId())],
                '-2 days',
            ],
            [
                $admin,
                'CREATE',
                'Category',
                $category->getId(),
                'Created Category',
                ['target' => sprintf('Category: %s (ID: %d)', $category->getName(), $category->getId())],
                '-2 days',
            ],
            [
                $staff,
                'CREATE',
                'Product',
                $product->getId(),
                'Created Product',
                [
                    'target' => sprintf('Product: %s (ID: %d)', $product->getName(), $product->getId()),
                    'details' => ['name' => $product->getName(), 'price' => (string) $product->getPrice()],
                ],
                '-1 day',
            ],
            [
                $staff,
                'CREATE',
                'Customer',
                $customer->getId(),
                'Created Customer',
                [
                    'target' => sprintf('Customer: %s (ID: %d)', $customer->getName(), $customer->getId()),
                    'details' => ['name' => $customer->getName(), 'email' => $customer->getEmail()],
                ],
                '-1 day',
            ],
            [
                $staff,
                'CREATE',
                'Order',
                $order->getId(),
                'Created Order',
                [
                    'target' => sprintf('Order (ID: %d)', $order->getId()),
                    'details' => [
                        'productName' => $order->getProductName(),
                        'status' => $order->getStatus(),
                    ],
                ],
                '-12 hours',
            ],
            [
                $staff,
                'STOCK_UPDATE',
                'Product',
                $product->getId(),
                sprintf("Stock updated for product '%s'", $product->getName()),
                [
                    'productName' => $product->getName(),
                    'productId' => $product->getId(),
                    'oldStock' => 100,
                    'newStock' => $product->getStock(),
                    'change' => $product->getStock() - 100,
                ],
                '-6 hours',
            ],
        ];

        foreach ($entries as [$user, $action, $entityType, $entityId, $description, $affectedData, $offset]) {
            $log = new ActivityLog();
            $log->setUser($user);
            $log->setAction($action);
            $log->setEntityType($entityType);
            $log->setEntityId($entityId);
            $log->setDescription($description);
            $log->setAffectedData(json_encode($affectedData, JSON_PRETTY_PRINT));
            $log->setIpAddress('127.0.0.1');
            $log->setTimestamp(new \DateTime($offset));
            $manager->persist($log);
        }

        $manager->flush();
    }
}
