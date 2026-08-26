<?php

namespace App\Controller;

use App\Entity\Marketing\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Désinscription marketing. Le token stable (unsubscribeToken) fait office
 * d'authentification : la personne agit depuis un email, sans être connectée.
 *
 *  - POST /api/unsubscribe/{token}          → one-click (en-tête List-Unsubscribe) :
 *    coupe tout le marketing (marketingConsent = false). Le transactionnel reste envoyé.
 *  - GET  /api/unsubscribe/{token}          → état courant pour la page front.
 *  - POST /api/unsubscribe/{token}/apply    → réglages fins depuis la page.
 */
class UnsubscribeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/unsubscribe/{token}', name: 'api_unsubscribe_oneclick', methods: ['POST'])]
    public function oneClick(string $token): JsonResponse
    {
        $user = $this->findUser($token);
        if ($user instanceof User) {
            $this->setMarketing($user, false);
            $this->em->flush();
        }

        // One-click doit renvoyer 2xx même si le token est inconnu (ne rien divulguer).
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/api/unsubscribe/{token}', name: 'api_unsubscribe_state', methods: ['GET'])]
    public function state(string $token): JsonResponse
    {
        $user = $this->findUser($token);
        if (!$user instanceof User) {
            return new JsonResponse(['found' => false]);
        }

        return new JsonResponse([
            'found' => true,
            'email' => $user->getEmail(),
            'marketing' => $user->hasMarketingConsent(),
            'notifications' => $this->activeFollowCount($user) > 0,
        ]);
    }

    #[Route('/api/unsubscribe/{token}/apply', name: 'api_unsubscribe_apply', methods: ['POST'])]
    public function apply(string $token, Request $request): JsonResponse
    {
        $user = $this->findUser($token);
        if (!$user instanceof User) {
            return new JsonResponse(['found' => false], 404);
        }

        $body = json_decode($request->getContent(), true) ?: [];
        if (array_key_exists('marketing', $body)) {
            $this->setMarketing($user, (bool) $body['marketing']);
        }
        if (array_key_exists('notifications', $body) && $body['notifications'] === false) {
            // Couper « le fait d'être informé » : désabonne tous les suivis produit/box.
            foreach ($this->follows($user) as $sub) {
                $sub->setStatus(Subscription::STATUS_UNSUBSCRIBED);
            }
        }
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    private function findUser(string $token): ?User
    {
        if ($token === '') {
            return null;
        }

        return $this->em->getRepository(User::class)->findOneBy(['unsubscribeToken' => $token]);
    }

    private function setMarketing(User $user, bool $value): void
    {
        $user->setMarketingConsent($value)->setMarketingConsentUpdatedAt(new \DateTimeImmutable());
    }

    /** @return Subscription[] */
    private function follows(User $user): array
    {
        return $this->em->getRepository(Subscription::class)->findBy(['user' => $user]);
    }

    private function activeFollowCount(User $user): int
    {
        return count($this->em->getRepository(Subscription::class)->findBy([
            'user' => $user,
            'status' => Subscription::STATUS_CONFIRMED,
        ]));
    }
}
