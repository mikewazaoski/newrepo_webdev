<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\Customer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEditMode = $options['edit_mode'] ?? false;
        $products = $options['products'] ?? [];
        
        $productChoices = [];
        $productAttrs = [];
        foreach ($products as $product) {
            $name = $product->getName();
            $productChoices[$name] = $name;
            $productAttrs[$name] = ['data-price' => $product->getPrice(), 'data-stock' => $product->getStock()];
        }
        
        $builder
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'name',
                'label' => 'Customer',
                'placeholder' => 'Select a customer',
                'required' => true,
                'disabled' => $isEditMode, // Disable in edit mode
            ])
            ->add('productName', ChoiceType::class, [
                'label' => 'Product Name',
                'choices' => $productChoices,
                'choice_attr' => $productAttrs,
                'placeholder' => 'Select a product',
                'required' => true,
                'disabled' => $isEditMode, // Disable in edit mode
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'Quantity',
                'scale' => 0,
                'attr' => [
                    'min' => 1,
                    'step' => 1,
                ],
                'disabled' => $isEditMode, // Disable in edit mode
            ])
            ->add('price', NumberType::class, [
                'label' => 'Price',
                'scale' => 2,
                'attr' => ['placeholder' => 'Enter price', 'readonly' => true], // Make readonly since auto-filled
                'disabled' => $isEditMode, // Disable in edit mode
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Pending' => 'pending',
                    'Shipped' => 'shipped',
                    'Processing' => 'processing',
                    'Delivered' => 'delivered',
                    'Cancelled' => 'cancelled',
                ],
                // Status is always editable
            ])
            ->add('orderDate', DateTimeType::class, [
                'label' => 'Order Date',
                'widget' => 'single_text',
                'disabled' => $isEditMode, // Disable in edit mode
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
            'csrf_protection' => true, // ✅ ensures CSRF token is generated
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'order_item', // unique ID for this form
            'edit_mode' => false, // Option to enable edit mode (only status editable)
            'products' => [], // Array of Product entities
        ]);
    }
}
