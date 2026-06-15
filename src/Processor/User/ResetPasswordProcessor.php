<?php

namespace App\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Model\User\PasswordResetResponse;
use App\Model\User\ResetPassword;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

readonly class ResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): PasswordResetResponse {
        if (!$data instanceof ResetPassword) {
            throw new \InvalidArgumentException('Invalid data type');
        }

        $user = $this->userRepository->findOneByPasswordResetTokenHash(hash('sha256', $data->token));
        $expiresAt = $user?->getPasswordResetExpiresAt();

        if ($user === null || $expiresAt === null || $expiresAt <= new \DateTimeImmutable()) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    message: 'Ce lien est invalide ou a expiré. Demandez un nouveau lien.',
                    messageTemplate: null,
                    parameters: [],
                    root: null,
                    propertyPath: 'token',
                    invalidValue: $data->token
                )
            ]));
        }

        $user
            ->setPassword($this->hasher->hashPassword($user, $data->password))
            ->setPasswordResetTokenHash(null)
            ->setPasswordResetExpiresAt(null);
        $this->em->flush();

        return new PasswordResetResponse('Votre mot de passe a été modifié.');
    }
}
