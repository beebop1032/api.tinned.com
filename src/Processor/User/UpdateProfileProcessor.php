<?php

namespace App\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Model\User\MeResponse;
use App\Model\User\UpdateProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class UpdateProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $em,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MeResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$data instanceof UpdateProfile) {
            throw new \LogicException('Authenticated user required.');
        }

        $user
            ->setFirstName(trim($data->firstName))
            ->setLastName(trim($data->lastName))
            ->setPhone(trim($data->phone))
            ->setMarketingConsent($data->marketingConsent)
            ->setMarketingConsentUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return new MeResponse(
            true,
            $user->getEmail(),
            $user->isActive(),
            $user->getFirstName(),
            $user->getLastName(),
            $user->getPhone(),
            $user->hasMarketingConsent(),
        );
    }
}
