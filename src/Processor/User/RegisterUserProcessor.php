<?php

namespace App\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\User;
use App\Model\User\RegisterUser;
use App\Model\User\RegisterUserResponse;
use App\Repository\UserRepository;
use App\Service\Marketing\ResendMailer;
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
        private UserRepository $userRepository,
        private ResendMailer $mailer,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(APP_FRONT_URL)%')]
        private string $frontUrl = 'http://localhost:4001',
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

        $email = strtolower(trim($data->email));

        $existing = $this->userRepository->findOneByEmail($email);
        if ($existing instanceof User && $existing->getPassword() !== null) {
            // Vrai compte (avec mot de passe) : on refuse le doublon.
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

        // Nouveau compte, ou « réclamation » d'un lead existant (email capturé sans mot de passe).
        $user = $existing instanceof User ? $existing : (new User())->setEmail($email);
        $user
            ->setFirstName(trim($data->firstName))
            ->setLastName(trim($data->lastName))
            ->setPhone(trim($data->phone))
            ->setPassword($this->hasher->hashPassword($user, $data->password))
            ->setRoles(['ROLE_USER'])
            ->setTermsAcceptedAt(new \DateTimeImmutable())
            ->setMarketingConsent($data->marketingConsent)
            ->setMarketingConsentUpdatedAt(new \DateTimeImmutable());

        if ($user->getUnsubscribeToken() === null) {
            $user->setUnsubscribeToken(bin2hex(random_bytes(24)));
        }

        // Vérification d'email requise, sauf si le lead avait déjà validé son email.
        $needsVerification = !$user->isEmailVerified();
        if ($needsVerification) {
            $user->setEmailVerifyToken(bin2hex(random_bytes(24)));
        }

        $this->em->persist($user);
        $this->em->flush();

        // Email de vérification (transactionnel). Ne doit jamais casser l'inscription.
        if ($needsVerification) {
            try {
                $verifyUrl = sprintf(
                    '%s/confirmer-email?token=%s',
                    rtrim($this->frontUrl, '/'),
                    rawurlencode((string) $user->getEmailVerifyToken()),
                );
                $this->mailer->sendVerification($user, $verifyUrl);
            } catch (\Throwable) {
                // Le mailer loggue déjà ses échecs.
            }
        }

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
