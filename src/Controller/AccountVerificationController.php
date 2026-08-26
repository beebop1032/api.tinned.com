<?php

namespace App\Controller;

use App\Entity\Marketing\Subscription;
use App\Entity\User;
use App\Service\Marketing\ResendMailer;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vérification d'email (le pivot) et définition de mot de passe pour les leads.
 *
 * La page front /confirmer-email?token=… POST vers verify-email (pas de GET à effet de
 * bord : évite l'auto-validation par les scanners d'email).
 */
class AccountVerificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResendMailer $mailer,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/account/verify-email', name: 'api_account_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $token = (string) (json_decode($request->getContent(), true)['token'] ?? '');
        $user = $token !== ''
            ? $this->em->getRepository(User::class)->findOneBy(['emailVerifyToken' => $token])
            : null;

        if (!$user instanceof User) {
            // Idempotence : un token déjà consommé n'est plus trouvable. On ne divulgue rien.
            return new JsonResponse(['verified' => false]);
        }

        $wasVerified = $user->isEmailVerified();
        $user->setEmailVerifiedAt(new \DateTimeImmutable());
        $user->setEmailVerifyToken(null);
        $this->em->flush();

        // Active les abonnements en attente de ce user, et envoie « c'est noté » pour chacun.
        if (!$wasVerified) {
            $this->activatePendingSubscriptions($user);
        }

        return new JsonResponse([
            'verified' => true,
            'hasPassword' => $user->getPassword() !== null,
            'email' => $user->getEmail(),
            'token' => $this->jwtManager->create($user), // connecte l'utilisateur côté front
        ]);
    }

    #[Route('/api/account/set-password', name: 'api_account_set_password', methods: ['POST'])]
    public function setPassword(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié.'], 401);
        }
        // Ce chemin ne SERT qu'à poser un premier mot de passe (lead). Un changement de
        // mot de passe existant passe par « mot de passe oublié ».
        if ($user->getPassword() !== null) {
            return new JsonResponse(['error' => 'Un mot de passe est déjà défini.'], 409);
        }

        $password = (string) (json_decode($request->getContent(), true)['password'] ?? '');
        if (strlen($password) < 8) {
            return new JsonResponse(['error' => 'Le mot de passe doit faire au moins 8 caractères.'], 422);
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    private function activatePendingSubscriptions(User $user): void
    {
        $pending = $this->em->getRepository(Subscription::class)->findBy([
            'user' => $user,
            'status' => Subscription::STATUS_PENDING,
        ]);

        foreach ($pending as $subscription) {
            $subscription->setStatus(Subscription::STATUS_CONFIRMED);
            $subscription->setConfirmedAt(new \DateTimeImmutable());
        }
        $this->em->flush();

        foreach ($pending as $subscription) {
            try {
                $this->mailer->sendWelcome($subscription);
                $this->mailer->sendEvent('subscription.confirmed', (string) $user->getEmail(), [
                    'targetType' => $subscription->getTargetType(),
                    'locale' => $subscription->getLocale(),
                ]);
            } catch (\Throwable) {
                // Le mailer loggue déjà ses échecs.
            }
        }
    }
}
