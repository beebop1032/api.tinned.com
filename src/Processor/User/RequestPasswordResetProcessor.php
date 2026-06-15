<?php

namespace App\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Model\User\PasswordResetResponse;
use App\Model\User\RequestPasswordReset;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

readonly class RequestPasswordResetProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private string $publicBaseUrl,
        private string $mailerFrom
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

            $this->mailer->send(
                (new Email())
                    ->from($this->mailerFrom)
                    ->to((string) $user->getEmail())
                    ->subject('Réinitialisez votre mot de passe Tinned')
                    ->text(
                        "Bonjour,\n\nPour choisir un nouveau mot de passe, ouvrez ce lien :\n"
                        .$link
                        ."\n\nCe lien expire dans une heure. Si vous n'avez pas demandé ce changement, ignorez cet email.\n\nTinned"
                    )
            );
        }

        return new PasswordResetResponse(
            'Si un compte correspond à cet email, un lien de réinitialisation a été envoyé.'
        );
    }
}
