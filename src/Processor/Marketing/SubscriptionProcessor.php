<?php

namespace App\Processor\Marketing;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Marketing\Subscription;
use App\Entity\User;
use App\Service\Marketing\ResendMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class SubscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ResendMailer $mailer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Subscription
    {
        if (!$data instanceof Subscription) {
            throw new \InvalidArgumentException('Expected a Subscription.');
        }

        // Authenticated subscriber: the account email is authoritative — never trust a
        // client-posted placeholder (also keeps dedup correct across different users).
        $authUser = $this->security->getUser();
        if ($authUser instanceof User && $authUser->getEmail()) {
            $data->setEmail($authUser->getEmail());
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

        // Opt-in simple : le consentement est donné au moment de l'action (case CGU
        // côté anonyme, ou le bouton « je veux être prévenu » côté connecté). Pas de
        // double opt-in : on confirme directement et on envoie un email « c'est noté ».
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $data->setUser($user);
        }
        $data->setStatus(Subscription::STATUS_CONFIRMED);
        $data->setConfirmedAt(new \DateTimeImmutable());
        $data->setConfirmToken(null);

        $this->em->persist($data);
        $this->em->flush();

        // Email « c'est noté / bienvenue » (contextuel produit / box / newsletter).
        $this->safeSend(fn () => $this->mailer->sendWelcome($data));
        // Déclenche une éventuelle séquence Resend (relances). Le welcome part en direct :
        // l'automation ne doit pas le redoubler.
        $this->safeSend(fn () => $this->mailer->sendEvent('subscription.confirmed', $data->getEmail(), [
            'targetType' => $data->getTargetType(),
            'locale' => $data->getLocale(),
        ]));

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
