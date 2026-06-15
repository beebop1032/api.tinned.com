<?php

namespace App\Controller\Payment;

use App\Service\Payment\MollieService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MollieWebhookController extends AbstractController
{
    #[Route('/api/payment/webhook', name: 'mollie_webhook', methods: ['POST'])]
    public function __invoke(Request $request, MollieService $mollieService): Response
    {
        $paymentId = $request->request->get('id') ?? '';
        if ($paymentId === '') {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        try {
            $mollieService->handleWebhook((string) $paymentId);
        } catch (\Throwable) {
            // Silently ignore — Mollie will retry
        }

        return new Response('', Response::HTTP_OK);
    }
}
