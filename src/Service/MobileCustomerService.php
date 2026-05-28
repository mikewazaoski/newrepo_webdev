<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\User;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;

class MobileCustomerService
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findForUser(User $user): ?Customer
    {
        $customer = $this->customerRepository->findOneByAccountUser($user);
        if ($customer instanceof Customer) {
            return $customer;
        }

        return $this->customerRepository->findOneByEmail((string) $user->getEmail());
    }

    public function getOrCreateForUser(User $user): Customer
    {
        $customer = $this->findForUser($user);
        if ($customer instanceof Customer) {
            if ($customer->getAccountUser() === null) {
                $customer->setAccountUser($user);
                $this->entityManager->flush();
            }

            return $customer;
        }

        return $this->createForUser($user);
    }

    public function createForUser(User $user): Customer
    {
        $username = $user->getUsername() ?? ('mobile_' . $user->getId());
        if ($this->customerRepository->findOneBy(['username' => $username]) instanceof Customer) {
            $username = $username . '_' . $user->getId();
        }

        $customer = new Customer();
        $customer->setAccountUser($user);
        $customer->setEmail((string) $user->getEmail());
        $customer->setName($user->getName() ?? $user->getEmail());
        $customer->setCustomerName($user->getName() ?? 'Mobile customer');
        $customer->setUsername($username);
        $customer->setCreatedBy($user);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }

    /**
     * @param array{name?: string, phone?: string|null, customerName?: string} $data
     */
    public function updateProfile(Customer $customer, array $data): Customer
    {
        if (isset($data['name']) && trim((string) $data['name']) !== '') {
            $name = trim((string) $data['name']);
            $customer->setName($name);
            if (!isset($data['customerName'])) {
                $customer->setCustomerName($name);
            }
        }

        if (array_key_exists('phone', $data)) {
            $phone = $data['phone'];
            $customer->setPhone($phone !== null && $phone !== '' ? trim((string) $phone) : null);
        }

        if (isset($data['customerName']) && trim((string) $data['customerName']) !== '') {
            $customer->setCustomerName(trim((string) $data['customerName']));
        }

        $this->entityManager->flush();

        return $customer;
    }

    public function serialize(Customer $customer): array
    {
        return [
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'customerName' => $customer->getCustomerName(),
            'username' => $customer->getUsername(),
            'orderCount' => $customer->getOrders()->count(),
        ];
    }
}
