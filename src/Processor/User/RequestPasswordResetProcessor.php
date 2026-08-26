<?php

namespace App\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Model\User\PasswordResetResponse;
use App\Model\User\RequestPasswordReset;
use App\Repository\UserRepository;
use App\Service\Marketing\ResendMailer;
use Doctrine\ORM\EntityManagerInterface;

readonly class RequestPasswordResetProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private ResendMailer $mailer,
        private string $publicBaseUrl,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): PasswordResetResponse {
        if (!$data instanceof RequestPasswordReset) {
            throw new \InvalidArgumentException('Invalid data type');
        }

        $user = $this->userRepository->findOneByEmail(trim($data->email));

        if ($user !== null && $user->isActive()) {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $user
                ->setPasswordResetTokenHash(hash('sha256', $token))
                ->setPasswordResetExpiresAt(new \DateTimeImmutable('+1 hour'));
            $this->em->flush();

            $link = sprintf(
                '%s/auth?reset=%s',
                rtrim($this->publicBaseUrl, '/'),
                rawurlencode($token)
            );

            // Envoi via Resend (ResendMailer ne lève jamais ; loggue ses échecs).
            $this->mailer->sendPasswordReset($user, $link);
        }

        return new PasswordResetResponse(
            'Si un compte correspond à cet email, un lien de réinitialisation a été envoyé.'
        );
    }
}
