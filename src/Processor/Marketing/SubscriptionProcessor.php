<?php

namespace App\Processor\Marketing;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Marketing\Subscription;
use App\Entity\User;
use App\Service\Marketing\ResendMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SubscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ResendMailer $mailer,
        #[Autowire('%env(APP_FRONT_URL)%')]
        private readonly string $frontUrl = 'http://localhost:4001',
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Subscription
    {
        if (!$data instanceof Subscription) {
            throw new \InvalidArgumentException('Expected a Subscription.');
        }

        $data->setEmail(strtolower(trim($data->getEmail())));

        // Dedup: reuse an existing, non-unsubscribed subscription for the same target.
        $existing = $this->em->getRepository(Subscription::class)->findOneBy([
            'email' => $data->getEmail(),
            'targetType' => $data->getTargetType(),
            'box' => $data->getBox(),
            'product' => $data->getProduct(),
        ]);
        if ($existing instanceof Subscription && $existing->getStatus() !== Subscription::STATUS_UNSUBSCRIBED) {
            return $existing;
        }

        $user = $this->security->getUser();

        if ($user instanceof User) {
            // Connected = 1-click opt-in.
            $data->setUser($user);
            $data->setStatus(Subscription::STATUS_CONFIRMED);
            $data->setConfirmedAt(new \DateTimeImmutable());
            $data->setConfirmToken(null);

            $this->em->persist($data);
            $this->em->flush();

            $this->safeSend(fn () => $this->mailer->sendWelcome($data));

            return $data;
        }

        // Anonymous = double opt-in.
        $data->setStatus(Subscription::STATUS_PENDING);
        $data->setConfirmToken(bin2hex(random_bytes(24)));

        $this->em->persist($data);
        $this->em->flush();

        $confirmUrl = sprintf(
            '%s/abonnement/confirme?token=%s',
            rtrim($this->frontUrl, '/'),
            rawurlencode((string) $data->getConfirmToken())
        );
        $this->safeSend(fn () => $this->mailer->sendConfirmation($data, $confirmUrl));

        return $data;
    }

    /**
     * Sending email must never break the subscribe flow.
     */
    private function safeSend(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable) {
            // Swallowed on purpose: the mailer already logs failures.
        }
    }
}
