<?php

namespace App\Service\Marketing;

use App\Entity\Marketing\Subscription;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Minimal Resend email client.
 *
 * Resilient by design: if RESEND_API_KEY is empty the mailer no-ops (logs and
 * returns false, no HTTP call). Any HTTP failure is caught and returns false —
 * it never throws, so a mail problem can never break a flow.
 *
 * Les envois marketing portent un lien + en-tête List-Unsubscribe ; le transactionnel
 * (vérification d'email) n'en a pas.
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
        #[Autowire('%env(APP_FRONT_URL)%')]
        private readonly string $frontUrl = 'http://localhost:4001',
        // Domaine public de l'API (sert aussi les assets email) — pour l'endpoint one-click.
        #[Autowire('%env(MAIL_ASSETS_URL)%')]
        private readonly string $apiUrl = 'https://api.tinned.com',
    ) {
    }

    /** Vérification d'email (transactionnel, pas de désinscription). */
    public function sendVerification(User $user, string $verifyUrl): bool
    {
        $mail = $this->renderer->verification($user, $verifyUrl);

        return $this->send((string) $user->getEmail(), $mail['subject'], $mail['html']);
    }

    /** « C'est noté / bienvenue » (marketing). */
    public function sendWelcome(Subscription $s): bool
    {
        $mail = $this->renderer->welcome($s, $this->unsubscribeUrl($s->getUser()));

        return $this->send($s->getEmail(), $mail['subject'], $mail['html'], $this->oneClickUrl($s->getUser()));
    }

    /** Le produit attendu est en ligne : envoyé aux inscrits « coming soon » (marketing). */
    public function sendLaunchLive(Subscription $s, string $productName, string $url): bool
    {
        $mail = $this->renderer->launchLive($s, $productName, $url, $this->unsubscribeUrl($s->getUser()));

        return $this->send($s->getEmail(), $mail['subject'], $mail['html'], $this->oneClickUrl($s->getUser()));
    }

    /** Le produit est de retour en stock (marketing). */
    public function sendBackInStock(Subscription $s, string $productName, string $url): bool
    {
        $mail = $this->renderer->backInStock($s, $productName, $url, $this->unsubscribeUrl($s->getUser()));

        return $this->send($s->getEmail(), $mail['subject'], $mail['html'], $this->oneClickUrl($s->getUser()));
    }

    /** Generic transactional send. Never throws; no-ops gracefully without an API key. */
    public function sendEmail(string $to, string $subject, string $html): bool
    {
        return $this->send($to, $subject, $html);
    }

    /** Lien humain de désinscription (page front avec réglages fins). */
    private function unsubscribeUrl(?User $user): ?string
    {
        $token = $user?->getUnsubscribeToken();

        return ($token === null || $token === '')
            ? null
            : sprintf('%s/desabonnement?token=%s', rtrim($this->frontUrl, '/'), rawurlencode($token));
    }

    /** Endpoint API one-click pour l'en-tête List-Unsubscribe (POST du client mail). */
    private function oneClickUrl(?User $user): ?string
    {
        $token = $user?->getUnsubscribeToken();

        return ($token === null || $token === '')
            ? null
            : sprintf('%s/api/unsubscribe/%s', rtrim($this->apiUrl, '/'), rawurlencode($token));
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

    private function send(string $to, string $subject, string $html, ?string $unsubscribeUrl = null): bool
    {
        if ($this->resendApiKey === '') {
            $this->logger->info('ResendMailer: RESEND_API_KEY absent, email non envoyé (no-op).', [
                'to' => $to,
                'subject' => $subject,
            ]);

            return false;
        }

        $payload = [
            'from' => $this->fromAddress,
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ];
        // List-Unsubscribe (RFC 8058) : bouton « Se désabonner » natif Gmail/Apple Mail.
        if ($unsubscribeUrl !== null) {
            $payload['headers'] = [
                'List-Unsubscribe' => sprintf('<%s>', $unsubscribeUrl),
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ];
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.resend.com/emails', [
                'auth_bearer' => $this->resendApiKey,
                'json' => $payload,
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
