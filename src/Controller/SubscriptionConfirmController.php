<?php

namespace App\Controller;

use App\Entity\Marketing\Subscription;
use App\Service\Marketing\ResendMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionConfirmController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ResendMailer $mailer,
    ) {
    }

    #[Route('/api/subscriptions/confirm/{token}', name: 'api_subscriptions_confirm', methods: ['GET'])]
    public function confirm(string $token): JsonResponse
    {
        $subscription = $this->em->getRepository(Subscription::class)->findOneBy(['confirmToken' => $token]);

        if (!$subscription instanceof Subscription || $subscription->getStatus() !== Subscription::STATUS_PENDING) {
            return new JsonResponse(['confirmed' => false]);
        }

        $subscription->setStatus(Subscription::STATUS_CONFIRMED);
        $subscription->setConfirmedAt(new \DateTimeImmutable());
        $subscription->setConfirmToken(null);
        $this->em->flush();

        try {
            $this->mailer->sendWelcome($subscription);
        } catch (\Throwable) {
            // Never break confirmation on a mail failure.
        }

        return new JsonResponse(['confirmed' => true]);
    }
}
