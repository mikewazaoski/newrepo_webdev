<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\User;
use App\Form\OrderType;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\CustomerRepository;
use App\Service\ActivityLogService;
use App\Service\MobilePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/order')]
#[IsGranted('ROLE_USER')]
final class OrderController extends AbstractController
{
    #[Route(name: 'app_order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        // Both staff and admins can see all orders
        $orders = $orderRepository->findAll();

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityLogService $logService, ProductRepository $productRepository, CustomerRepository $customerRepository): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        
        // Check if the logged-in user has a customer record, if not, create one
        $customer = $customerRepository->findOneBy(['username' => $user->getUsername()]);
        if (!$customer) {
            $customer = new Customer();
            $customer->setName($user->getName());
            $customer->setEmail($user->getEmail());
            $customer->setUsername($user->getUsername());
            $customer->setCustomerName($user->getName()); // Assuming customerName is the same as name
            $customer->setCreatedBy($user);
            $entityManager->persist($customer);
            $entityManager->flush();
        }
        
        $order = new Order();
        $products = $productRepository->findAll(); // Get all products for dropdown
        $form = $this->createForm(OrderType::class, $order, ['products' => $products]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $productName = (string) $order->getProductName();
            $quantity = (int) $order->getQuantity();

            if ($productName === '') {
                $this->addFlash('error', 'Product name is required.');
                return $this->render('order/new.html.twig', [
                    'order' => $order,
                    'form' => $form,
                ]);
            }

            if ($quantity <= 0) {
                $this->addFlash('error', 'Quantity must be greater than 0.');
                return $this->render('order/new.html.twig', [
                    'order' => $order,
                    'form' => $form,
                ]);
            }

            // Deduct stock from the matching product by name.
            $product = $productRepository->findOneBy(['name' => $productName]);

            if (!$product) {
                $this->addFlash('error', 'No product found for that product name. Stock could not be deducted.');

                return $this->render('order/new.html.twig', [
                    'order' => $order,
                    'form' => $form,
                ]);
            }

            $availableStock = $product->getStock();
            if ($availableStock < $quantity) {
                $this->addFlash('error', sprintf('Not enough stock. Available: %d, requested: %d.', $availableStock, $quantity));

                return $this->render('order/new.html.twig', [
                    'order' => $order,
                    'form' => $form,
                ]);
            }

            $product->setStock($availableStock - $quantity);
            $entityManager->persist($product);

            // Set createdBy for ownership tracking
            $order->setCreatedBy($this->getUser());
            
            // Set orderDate if not already set
            if (!$order->getOrderDate()) {
                $order->setOrderDate(new \DateTime());
            }

            $entityManager->persist($order);
            $entityManager->flush();

            $logService->logCreate($this->getUser(), 'Order', $order->getId(), [
                'productName' => $order->getProductName(),
                'quantity' => (string) $order->getQuantity()
            ]);

            // Track stock changes for auditing.
            $logService->logUpdate($this->getUser(), 'Product', $product->getId(), [
                'name' => $product->getName(),
                'stock_change' => '-' . (string) $quantity,
                'stock_after' => (string) $product->getStock(),
            ]);

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/mark-paid', name: 'app_order_mark_paid', methods: ['POST'])]
    public function markPaid(
        Request $request,
        Order $order,
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager,
        ActivityLogService $logService,
    ): Response {
        if (!$this->isCsrfTokenValid('order_action' . $order->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_index');
        }

        $orders = $this->ordersInSameBatch($order, $orderRepository);
        foreach ($orders as $line) {
            if ($line->getPaymentStatus() !== MobilePaymentService::STATUS_PAID) {
                $line->setPaymentStatus(MobilePaymentService::STATUS_PAID);
                $line->setPaidAt(new \DateTime());
            }
        }

        $entityManager->flush();
        $logService->logUpdate($this->getUser(), 'Order', $order->getId(), ['action' => 'mark_paid']);
        $this->addFlash('success', 'Payment marked as paid.');

        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/{id}/approve', name: 'app_order_approve', methods: ['POST'])]
    public function approve(
        Request $request,
        Order $order,
        OrderRepository $orderRepository,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager,
        ActivityLogService $logService,
    ): Response {
        if (!$this->isCsrfTokenValid('order_action' . $order->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_index');
        }

        $orders = $this->ordersInSameBatch($order, $orderRepository);
        foreach ($orders as $line) {
            if ($line->getStatus() !== 'pending') {
                continue;
            }
            $product = $line->getProductId()
                ? $productRepository->find($line->getProductId())
                : null;
            if ($product instanceof Product) {
                $qty = (int) $line->getQuantity();
                if ($product->getStock() < $qty) {
                    $this->addFlash('error', sprintf(
                        'Cannot ship "%s": only %d in stock.',
                        $product->getName(),
                        $product->getStock()
                    ));
                    return $this->redirectToRoute('app_order_index');
                }
                $product->setStock($product->getStock() - $qty);
            }
            $line->setStatus('shipped');
        }

        $entityManager->flush();
        $logService->logUpdate($this->getUser(), 'Order', $order->getId(), [
            'action' => 'approve_ship',
            'orderRef' => $order->getOrderRef(),
        ]);
        $this->addFlash('success', 'Order approved and marked as shipped.');

        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/{id}/reject', name: 'app_order_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        Order $order,
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager,
        ActivityLogService $logService,
    ): Response {
        if (!$this->isCsrfTokenValid('order_action' . $order->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_order_index');
        }

        $orders = $this->ordersInSameBatch($order, $orderRepository);
        foreach ($orders as $line) {
            if ($line->getStatus() === 'pending') {
                $line->setStatus('cancelled');
            }
        }

        $entityManager->flush();
        $logService->logUpdate($this->getUser(), 'Order', $order->getId(), [
            'action' => 'reject_cancel',
            'orderRef' => $order->getOrderRef(),
        ]);
        $this->addFlash('success', 'Order cancelled.');

        return $this->redirectToRoute('app_order_index');
    }

    /**
     * @return Order[]
     */
    private function ordersInSameBatch(Order $order, OrderRepository $orderRepository): array
    {
        if ($order->getOrderRef()) {
            return $orderRepository->findBy(['orderRef' => $order->getOrderRef()]);
        }

        return [$order];
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        // Both staff and admins can view any order
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $entityManager, ActivityLogService $logService): Response
    {
        // Both staff and admins can edit any order

        // Create form with edit_mode enabled (only status will be editable)
        $form = $this->createForm(OrderType::class, $order, ['edit_mode' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Only update the status field (other fields are disabled and won't be submitted)
            $status = $form->get('status')->getData();
            
            if ($status !== null) {
                $order->setStatus((string) $status);
            }
            
            $entityManager->flush();

            $logService->logUpdate($this->getUser(), 'Order', $order->getId(), [
                'productName' => $order->getProductName(),
                'status' => $order->getStatus()
            ]);

            $this->addFlash('success', 'Order status updated successfully!');
            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager, ActivityLogService $logService): Response
    {
        // Both staff and admins can delete any order

        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->getPayload()->getString('_token'))) {
            $orderId = $order->getId();
            $orderData = [
                'productName' => $order->getProductName(),
                'quantity' => (string) $order->getQuantity()
            ];
            $entityManager->remove($order);
            $entityManager->flush();
            $logService->logDelete($this->getUser(), 'Order', $orderId, $orderData);
        }

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }
}
