<?php

namespace App\Service\Marketing;

use App\Entity\Marketing\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Minimal Resend transactional email client.
 *
 * Resilient by design: if RESEND_API_KEY is empty the mailer no-ops (logs and
 * returns false, no HTTP call). Any HTTP failure is caught and returns false —
 * it never throws, so a mail problem can never break a subscription.
 */
class ResendMailer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly EmailRenderer $renderer,
        #[Autowire('%env(string:RESEND_API_KEY)%')]
        private readonly string $resendApiKey = '',
        #[Autowire('%env(RESEND_FROM)%')]
        private readonly string $fromAddress = 'Tinned <onboarding@resend.dev>',
    ) {
    }

    public function sendWelcome(Subscription $s): bool
    {
        $mail = $this->renderer->welcome($s);

        return $this->send($s->getEmail(), $mail['subject'], $mail['html']);
    }

    /** Email de bienvenue à la création de compte. */
    public function sendAccountWelcome(string $email, string $firstName = ''): bool
    {
        $mail = $this->renderer->accountWelcome($firstName);

        return $this->send($email, $mail['subject'], $mail['html']);
    }

    /** Le produit attendu est en ligne : envoyé aux inscrits « coming soon ». */
    public function sendLaunchLive(Subscription $s, string $productName, string $url): bool
    {
        $mail = $this->renderer->launchLive($s, $productName, $url);

        return $this->send($s->getEmail(), $mail['subject'], $mail['html']);
    }

    /** Le produit est de retour en stock : envoyé aux inscrits « épuisé ». */
    public function sendBackInStock(Subscription $s, string $productName, string $url): bool
    {
        $mail = $this->renderer->backInStock($s, $productName, $url);

        return $this->send($s->getEmail(), $mail['subject'], $mail['html']);
    }

    /** Generic transactional send. Never throws; no-ops gracefully without an API key. */
    public function sendEmail(string $to, string $subject, string $html): bool
    {
        return $this->send($to, $subject, $html);
    }

    /**
     * Déclenche une Automation Resend (séquence event-driven) pour un contact.
     * Résilient comme send() : no-op si la clé est absente, ne lève jamais.
     *
     * @param array<string, mixed> $payload
     */
    public function sendEvent(string $event, string $email, array $payload = []): bool
    {
        if ($this->resendApiKey === '') {
            $this->logger->info('ResendMailer: RESEND_API_KEY absent, event non envoyé (no-op).', [
                'event' => $event,
                'email' => $email,
            ]);

            return false;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.resend.com/events/send', [
                'auth_bearer' => $this->resendApiKey,
                'json' => array_filter([
                    'event' => $event,
                    'email' => $email,
                    'payload' => $payload ?: null,
                ], static fn ($v) => $v !== null),
            ]);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return true;
            }

            $this->logger->error('ResendMailer: réponse non-2xx sur events/send.', [
                'event' => $event,
                'email' => $email,
                'status' => $status,
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('ResendMailer: échec events/send.', [
                'event' => $event,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function send(string $to, string $subject, string $html): bool
    {
        if ($this->resendApiKey === '') {
            $this->logger->info('ResendMailer: RESEND_API_KEY absent, email non envoyé (no-op).', [
                'to' => $to,
                'subject' => $subject,
            ]);

            return false;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.resend.com/emails', [
                'auth_bearer' => $this->resendApiKey,
                'json' => [
                    'from' => $this->fromAddress,
                    'to' => [$to],
                    'subject' => $subject,
                    'html' => $html,
                ],
            ]);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return true;
            }

            $this->logger->error('ResendMailer: réponse non-2xx de Resend.', [
                'to' => $to,
                'status' => $status,
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('ResendMailer: échec d\'envoi.', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
