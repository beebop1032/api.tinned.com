<?php

namespace App\Controller;

use App\Service\Payment\MollieService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class PaymentMethodsController extends AbstractController
{
    public function __construct(
        private readonly MollieService $mollie,
    ) {}

    /**
     * Public: the checkout form lists the methods Mollie has enabled for this cart amount
     * and billing country, in the buyer's locale. Returns an empty list (never an error)
     * when Mollie can't be reached, so the front falls back to the hosted-Mollie option.
     */
    #[Route('/api/payment-methods', name: 'api_payment_methods', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $amountCents = max(0, (int) $request->query->get('amount', '0'));

        $country = strtoupper(substr((string) $request->query->get('country', 'BE'), 0, 2));
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            $country = 'BE';
        }

        // Mollie expects a locale like fr_BE / nl_BE / en_US; fall back to fr_BE on anything else.
        $locale = (string) $request->query->get('locale', 'fr_BE');
        if (!preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
            $locale = 'fr_BE';
        }

        return new JsonResponse([
            'methods' => $this->mollie->enabledMethods($amountCents, 'EUR', $country, $locale),
            // Public profile id (pfl_...) so the front can embed the card fields without a
            // hand-set env var. Null when Mollie is unreachable → front skips embedded card.
            'profileId' => $this->mollie->profileId(),
            // mollie.js must run in the same mode as the API key; derived server-side so the
            // front needs no Mollie config of its own.
            'testmode' => $this->mollie->isTestMode(),
        ]);
    }
}
