<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/customer')]
#[IsGranted('ROLE_USER')]
class CustomerController extends AbstractController
{
    #[Route('/', name: 'app_customer_index', methods: ['GET'])]
    public function index(CustomerRepository $customerRepository): Response
    {
        // Both staff and admins can see all customers
        $customers = $customerRepository->findAll();

        return $this->render('customer/index.html.twig', [
            'customers' => $customers,
        ]);
    }

    #[Route('/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogService $logService): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set createdBy for ownership tracking
            $customer->setCreatedBy($this->getUser());
            
            // Ensure customerName is set (required field) - always set from name
            $customerName = $customer->getName() ?: 'Customer';
            $customer->setCustomerName($customerName);
            
            // Ensure username is set (required field) - use name or email as fallback
            if (!$customer->getUsername()) {
                $username = $customer->getName() ?: ($customer->getEmail() ?: 'customer_' . time());
                $customer->setUsername($username);
            }

            $entityManager->persist($customer);
            $entityManager->flush();

            $logService->logCreate($this->getUser(), 'Customer', $customer->getId(), ['name' => $customer->getName(), 'email' => $customer->getEmail()]);

            $this->addFlash('success', 'Customer created successfully.');
            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_show', methods: ['GET'])]
    public function show(Customer $customer): Response
    {
        // Both staff and admins can view any customer
        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager, ActivityLogService $logService): Response
    {
        // Both staff and admins can edit any customer

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure customerName is set (required field) - always set from name
            if ($customer->getName()) {
                $customer->setCustomerName($customer->getName());
            }
            
            // Ensure username is set (required field)
            if (!$customer->getUsername() && $customer->getName()) {
                $customer->setUsername($customer->getName());
            }
            
            $entityManager->flush();

            $logService->logUpdate($this->getUser(), 'Customer', $customer->getId(), ['name' => $customer->getName(), 'email' => $customer->getEmail()]);

            $this->addFlash('success', 'Customer updated successfully.');
            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager, ActivityLogService $logService): Response
    {
        // Both staff and admins can delete any customer

        if ($this->isCsrfTokenValid('delete'.$customer->getId(), $request->request->get('_token'))) {
            $customerId = $customer->getId();
            $customerData = ['name' => $customer->getName(), 'email' => $customer->getEmail()];
            $entityManager->remove($customer);
            $entityManager->flush();
            $logService->logDelete($this->getUser(), 'Customer', $customerId, $customerData);
            $this->addFlash('success', 'Customer deleted successfully.');
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }
}
