<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CustomerFixtures extends Fixture implements DependentFixtureInterface
{
    public const REF_ALICE = 'customer.alice';
    public const REF_BOB = 'customer.bob';
    public const REF_CAROL = 'customer.carol';

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $staff = $this->getReference(UserFixtures::REF_STAFF, User::class);

        $customers = [
            [
                self::REF_ALICE,
                'Alice Johnson',
                'alice.johnson@example.com',
                '+1-555-0101',
                'alice_j',
            ],
            [
                self::REF_BOB,
                'Bob Martinez',
                'bob.martinez@example.com',
                '+1-555-0102',
                'bob_m',
            ],
            [
                self::REF_CAROL,
                'Carol Williams',
                'carol.williams@example.com',
                '+1-555-0103',
                'carol_w',
            ],
        ];

        foreach ($customers as [$ref, $name, $email, $phone, $username]) {
            $customer = new Customer();
            $customer->setName($name);
            $customer->setCustomerName($name);
            $customer->setEmail($email);
            $customer->setPhone($phone);
            $customer->setUsername($username);
            $customer->setCreatedBy($staff);
            $manager->persist($customer);
            $this->addReference($ref, $customer);
        }

        $manager->flush();
    }
}
