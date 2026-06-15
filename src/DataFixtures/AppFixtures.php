<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        $admin = (new User())
            ->setEmail('admin@tinned.local')
            ->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'ChangeMe123!'));

        $manager->persist($admin);
        $manager->flush();
    }
}
