<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    public const REF_PREMIUM_DOG_KIBBLE = 'product.premium-dog-kibble';
    public const REF_PUPPY_TREATS = 'product.puppy-treats';
    public const REF_CAT_LITTER = 'product.cat-litter';
    public const REF_CAT_SCRATCHER = 'product.cat-scratcher';
    public const REF_BIRD_SEED_MIX = 'product.bird-seed-mix';
    public const REF_AQUARIUM_FILTER = 'product.aquarium-filter';
    public const REF_FISH_FLAKES = 'product.fish-flakes';

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $staff = $this->getReference(UserFixtures::REF_STAFF, User::class);

        $products = [
            [
                self::REF_PREMIUM_DOG_KIBBLE,
                'Premium Dog Kibble 15kg',
                49.99,
                'High-protein dry food for adult dogs.',
                'Gemini-Generated-Image-9s3eo19s3eo19s3e-69d54d1dbb93f.png',
                CategoryFixtures::REF_DOG_FOOD,
                120,
            ],
            [
                self::REF_PUPPY_TREATS,
                'Puppy Training Treats',
                12.50,
                'Small soft treats ideal for training sessions.',
                'Gemini-Generated-Image-v5x38qv5x38qv5x3-69d54772dd76e.png',
                CategoryFixtures::REF_DOG_FOOD,
                85,
            ],
            [
                self::REF_CAT_LITTER,
                'Clumping Cat Litter 10kg',
                18.75,
                'Low-dust clumping litter with odor control.',
                'Gemini-Generated-Image-vmwph7vmwph7vmwp-69d549629f808.png',
                CategoryFixtures::REF_CAT_SUPPLIES,
                60,
            ],
            [
                self::REF_CAT_SCRATCHER,
                'Cardboard Cat Scratcher',
                24.00,
                'Durable scratcher pad with catnip included.',
                'Gemini-Generated-Image-jylc9gjylc9gjylc-69d54ed04809e.jpg',
                CategoryFixtures::REF_CAT_SUPPLIES,
                40,
            ],
            [
                self::REF_BIRD_SEED_MIX,
                'Wild Bird Seed Mix 5kg',
                15.99,
                'Blend of sunflower seeds and millet for garden birds.',
                'Gemini-Generated-Image-lmpv4olmpv4olmpv-69d551233aae1.jpg',
                CategoryFixtures::REF_BIRD_CARE,
                55,
            ],
            [
                self::REF_AQUARIUM_FILTER,
                'Aquarium Filter Medium',
                34.50,
                'Quiet internal filter for tanks up to 200 litres.',
                'Gemini-Generated-Image-yvdos8yvdos8yvdo-6a0eb7fa7cc5a.jpg',
                CategoryFixtures::REF_FISH_AQUATICS,
                25,
            ],
            [
                self::REF_FISH_FLAKES,
                'Tropical Fish Flakes',
                8.99,
                'Daily flake food for tropical freshwater fish.',
                'Gemini-Generated-Image-q23nvdq23nvdq23n-69d55224580e2.jpg',
                CategoryFixtures::REF_FISH_AQUATICS,
                90,
            ],
        ];

        foreach ($products as [$ref, $name, $price, $description, $image, $categoryRef, $stock]) {
            $product = new Product();
            $product->setName($name);
            $product->setPrice($price);
            $product->setDescription($description);
            $product->setImage($image);
            $product->setStock($stock);
            $product->setCategory($this->getReference($categoryRef, Category::class));
            $product->setCreatedBy($staff);
            $manager->persist($product);
            $this->addReference($ref, $product);
        }

        $manager->flush();
    }
}
