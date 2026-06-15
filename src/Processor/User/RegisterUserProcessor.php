<?php

namespace App\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\User;
use App\Model\User\RegisterUser;
use App\Model\User\RegisterUserResponse;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

readonly class RegisterUserProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private JWTTokenManagerInterface $jwtManager,
        private UserRepository $userRepository
    ) {}

    /**
     * @throws RandomException
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): RegisterUserResponse {
        if (!$data instanceof RegisterUser) {
            throw new \InvalidArgumentException('Invalid data type');
        }

        $email = trim($data->email);

        if($this->userRepository->findOneByEmail($email)) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    message: "Un compte existe déjà avec cette adresse email. Connectez-vous ou réinitialisez votre mot de passe.",
                    messageTemplate: null,
                    parameters: [],
                    root: null,
                    propertyPath: "email",
                    invalidValue: $email
                )
            ]);

            throw new ValidationException($violations);
        }

        $user = new User();
        $user
            ->setEmail($email)
            ->setFirstName(trim($data->firstName))
            ->setLastName(trim($data->lastName))
            ->setPhone(trim($data->phone))
            ->setPassword($this->hasher->hashPassword($user, $data->password))
            ->setRoles(['ROLE_USER'])
            ->setTermsAcceptedAt(new \DateTimeImmutable())
            ->setMarketingConsent($data->marketingConsent)
            ->setMarketingConsentUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($user);
        $this->em->flush();

        $token = $this->jwtManager->create($user);

        return new RegisterUserResponse(
            $user->getId(),
            $user->getEmail(),
            $token,
            (string) $user->getFirstName(),
            (string) $user->getLastName(),
            (string) $user->getPhone()
        );
    }
}
