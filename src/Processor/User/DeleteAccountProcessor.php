<?php

namespace App\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class DeleteAccountProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('Authenticated user required.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
        $user->setEmail($this->buildPrivacyEmail($user));
        $user->setActive(false);

        $this->entityManager->flush();
    }

    private function buildPrivacyEmail(User $user): string
    {
        $email = (string) $user->getEmail();

        if (!str_contains($email, '@')) {
            return sprintf('%s+dataprivacy-%d@deleted.local', $email, $user->getId() ?? 0);
        }

        [$localPart, $domain] = explode('@', $email, 2);

        return sprintf('%s+dataprivacy-%d@%s', $localPart, $user->getId() ?? 0, $domain);
    }
}
