<?php

namespace App\Controller;

use App\Entity\Shopping\Coupon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class CouponValidateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/coupons/validate', name: 'api_coupons_validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '{}', true);
        $payload = is_array($payload) ? $payload : [];

        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        $subtotalCents = max(0, (int) ($payload['subtotalCents'] ?? 0));

        $invalid = static fn (string $message): JsonResponse => new JsonResponse([
            'valid' => false,
            'discountCents' => 0,
            'type' => null,
            'value' => null,
            'message' => $message,
        ]);

        if ($code === '') {
            return $invalid('Veuillez saisir un code promo.');
        }

        $coupon = $this->em->getRepository(Coupon::class)->findOneBy(['code' => $code]);
        if (!$coupon instanceof Coupon || !$coupon->isValidNow()) {
            return $invalid('Ce code promo est invalide ou expiré.');
        }

        if ($subtotalCents < $coupon->getMinSubtotalCents()) {
            return $invalid(sprintf(
                'Ce code requiert un minimum de %s €.',
                number_format($coupon->getMinSubtotalCents() / 100, 2, ',', ' ')
            ));
        }

        return new JsonResponse([
            'valid' => true,
            'discountCents' => $coupon->discountFor($subtotalCents),
            'type' => $coupon->getType(),
            'value' => $coupon->getValue(),
            'message' => 'Code promo appliqué.',
        ]);
    }
}
