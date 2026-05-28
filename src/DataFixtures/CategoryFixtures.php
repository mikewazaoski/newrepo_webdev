<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture implements DependentFixtureInterface
{
    public const REF_DOG_FOOD = 'category.dog-food';
    public const REF_CAT_SUPPLIES = 'category.cat-supplies';
    public const REF_BIRD_CARE = 'category.bird-care';
    public const REF_FISH_AQUATICS = 'category.fish-aquatics';

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference(UserFixtures::REF_ADMIN, \App\Entity\User::class);

        $categories = [
            [self::REF_DOG_FOOD, 'Dog Food'],
            [self::REF_CAT_SUPPLIES, 'Cat Supplies'],
            [self::REF_BIRD_CARE, 'Bird Care'],
            [self::REF_FISH_AQUATICS, 'Fish & Aquatics'],
        ];

        foreach ($categories as [$ref, $name]) {
            $category = new Category();
            $category->setName($name);
            $category->setCreatedBy($admin);
            $manager->persist($category);
            $this->addReference($ref, $category);
        }

        $manager->flush();
    }
}
