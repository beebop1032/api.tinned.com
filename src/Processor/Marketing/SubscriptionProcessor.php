<?php

namespace App\Processor\Marketing;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Marketing\Subscription;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Marketing\ResendMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Suivre un produit/box ou s'inscrire à la newsletter. Modèle centré compte :
 * toute adresse crée (ou réutilise) un User (lead si sans mot de passe), non vérifié
 * jusqu'au clic sur le lien de vérification.
 *
 *  - Email déjà vérifié  → abonnement confirmé direct + email « c'est noté ».
 *  - Email non vérifié   → abonnement en attente + email de vérification. L'abonnement
 *    est activé (et « c'est noté » envoyé) au moment où l'email est confirmé
 *    (cf. EmailVerificationController).
 */
class SubscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ResendMailer $mailer,
        private readonly UserRepository $userRepository,
        #[Autowire('%env(APP_FRONT_URL)%')]
        private readonly string $frontUrl = 'http://localhost:4001',
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Subscription
    {
        if (!$data instanceof Subscription) {
            throw new \InvalidArgumentException('Expected a Subscription.');
        }

        // L'email du compte authentifié fait foi ; sinon on normalise l'email posté.
        $authUser = $this->security->getUser();
        if ($authUser instanceof User && $authUser->getEmail()) {
            $data->setEmail($authUser->getEmail());
        }
        $email = strtolower(trim($data->getEmail()));
        $data->setEmail($email);

        // Dedup : réutilise un abonnement existant non désabonné pour la même cible.
        $existing = $this->em->getRepository(Subscription::class)->findOneBy([
            'email' => $email,
            'targetType' => $data->getTargetType(),
            'box' => $data->getBox(),
            'product' => $data->getProduct(),
        ]);
        if ($existing instanceof Subscription && $existing->getStatus() !== Subscription::STATUS_UNSUBSCRIBED) {
            return $existing;
        }

        // Résout le User : compte connecté, lead existant, ou nouveau lead.
        $user = $authUser instanceof User ? $authUser : $this->userRepository->findOneByEmail($email);
        $newVerificationNeeded = false;

        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail($email)
                ->setRoles(['ROLE_USER'])
                ->setUnsubscribeToken(bin2hex(random_bytes(24)))
                ->setEmailVerifyToken(bin2hex(random_bytes(24)));
            $this->em->persist($user);
            $newVerificationNeeded = true;
        } else {
            if ($user->getUnsubscribeToken() === null) {
                $user->setUnsubscribeToken(bin2hex(random_bytes(24)));
            }
            if (!$user->isEmailVerified() && $user->getEmailVerifyToken() === null) {
                $user->setEmailVerifyToken(bin2hex(random_bytes(24)));
                $newVerificationNeeded = true;
            } elseif (!$user->isEmailVerified()) {
                $newVerificationNeeded = true;
            }
        }

        // Consentement donné au moment de l'action → opt-in marketing (jamais de downgrade ici).
        if ($data->isConsentTinned() && !$user->hasMarketingConsent()) {
            $user->setMarketingConsent(true)->setMarketingConsentUpdatedAt(new \DateTimeImmutable());
        }

        $data->setUser($user);
        $verified = $user->isEmailVerified();
        $data->setStatus($verified ? Subscription::STATUS_CONFIRMED : Subscription::STATUS_PENDING);
        if ($verified) {
            $data->setConfirmedAt(new \DateTimeImmutable());
        }

        $this->em->persist($data);
        $this->em->flush();

        if ($verified) {
            // « C'est noté » immédiat + éventuelle séquence Resend.
            $this->safeSend(fn () => $this->mailer->sendWelcome($data));
            $this->safeSend(fn () => $this->mailer->sendEvent('subscription.confirmed', $email, [
                'targetType' => $data->getTargetType(),
                'locale' => $data->getLocale(),
            ]));
        } elseif ($newVerificationNeeded) {
            // Email de vérification : la validation activera l'abonnement en attente.
            $this->safeSend(fn () => $this->mailer->sendVerification($user, sprintf(
                '%s/confirmer-email?token=%s',
                rtrim($this->frontUrl, '/'),
                rawurlencode((string) $user->getEmailVerifyToken()),
            )));
        }

        return $data;
    }

    /** L'envoi d'email ne doit jamais casser le flux d'abonnement. */
    private function safeSend(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable) {
            // Le mailer loggue déjà ses échecs.
        }
    }
}
