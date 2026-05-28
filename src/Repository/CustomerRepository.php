<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function findOneByAccountUser(User $user): ?Customer
    {
        return $this->findOneBy(['accountUser' => $user]);
    }

    public function findOneByEmail(string $email): ?Customer
    {
        return $this->findOneBy(['email' => $email]);
    }
}
