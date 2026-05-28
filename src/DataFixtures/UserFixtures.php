<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seeds representative user accounts for local development and testing.
 *
 * All accounts are verified, active (except inactive), and use password: password
 *
 * | Username  | Email                      | Roles        | Active |
 * |-----------|----------------------------|--------------|--------|
 * | admin     | admin@fixtures.local       | ROLE_ADMIN   | yes    |
 * | staff     | staff@fixtures.local       | ROLE_STAFF   | yes    |
 * | customer  | customer@fixtures.local    | ROLE_USER    | yes    |
 * | inactive  | inactive@fixtures.local    | ROLE_USER    | no     |
 *
 * Load with: php bin/console doctrine:fixtures:load
 */
class UserFixtures extends Fixture
{
    public const REF_ADMIN = 'user.admin';
    public const REF_STAFF = 'user.staff';
    public const REF_CUSTOMER = 'user.customer';
    public const REF_INACTIVE = 'user.inactive';

    private const DEFAULT_PASSWORD = 'password';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->createUser(
            email: 'admin@fixtures.local',
            username: 'admin',
            name: 'Admin User',
            roles: ['ROLE_ADMIN'],
        );
        $manager->persist($admin);
        $this->addReference(self::REF_ADMIN, $admin);

        $staff = $this->createUser(
            email: 'staff@fixtures.local',
            username: 'staff',
            name: 'Staff User',
            roles: ['ROLE_STAFF'],
        );
        $manager->persist($staff);
        $this->addReference(self::REF_STAFF, $staff);

        $customer = $this->createUser(
            email: 'customer@fixtures.local',
            username: 'customer',
            name: 'Customer User',
            roles: [],
        );
        $manager->persist($customer);
        $this->addReference(self::REF_CUSTOMER, $customer);

        $inactive = $this->createUser(
            email: 'inactive@fixtures.local',
            username: 'inactive',
            name: 'Inactive User',
            roles: [],
            isActive: false,
        );
        $manager->persist($inactive);
        $this->addReference(self::REF_INACTIVE, $inactive);

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(
        string $email,
        string $username,
        string $name,
        array $roles,
        bool $isActive = true,
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setName($name);
        $user->setRoles($roles);
        $user->setIsActive($isActive);
        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD)
        );

        return $user;
    }
}
