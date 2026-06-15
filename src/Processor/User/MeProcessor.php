<?php

namespace App\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Model\User\MeResponse;
use Random\RandomException;
use Symfony\Bundle\SecurityBundle\Security;

readonly class MeProcessor implements ProcessorInterface
{
    public function __construct(private Security $security) {}

    /**
     * @throws RandomException
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): MeResponse {

        /** @var User $user */
        $user = $this->security->getUser();

        return new MeResponse(
            $user instanceof User,
            $user?->getEmail(),
            $user?->isActive(),
            $user?->getFirstName(),
            $user?->getLastName(),
            $user?->getPhone(),
            $user?->hasMarketingConsent(),
        );
    }
}
