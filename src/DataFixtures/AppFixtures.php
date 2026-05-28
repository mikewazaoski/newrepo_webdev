<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Entry-point fixture — loads all entity fixtures via the dependency chain.
 */
class AppFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CategoryFixtures::class,
            ProductFixtures::class,
            CustomerFixtures::class,
            OrderFixtures::class,
            ActivityLogFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Entity data is loaded by individual fixture classes.
    }
}
