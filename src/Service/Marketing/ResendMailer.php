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
        #[Autowire('%env(string:RESEND_API_KEY)%')]
        private readonly string $resendApiKey = '',
        #[Autowire('%env(RESEND_FROM)%')]
        private readonly string $fromAddress = 'Tinned <onboarding@resend.dev>',
    ) {
    }

    public function sendConfirmation(Subscription $s, string $confirmUrl): bool
    {
        $html = sprintf(
            '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1a1a1a">'
            .'<h2>Confirmez votre abonnement</h2>'
            .'<p>Bonjour,</p>'
            .'<p>Merci de votre intérêt pour Tinned. Pour finaliser votre abonnement, cliquez sur le lien ci-dessous :</p>'
            .'<p><a href="%s" style="display:inline-block;padding:12px 24px;background:#E8A33D;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">Confirmer mon abonnement</a></p>'
            .'<p style="color:#666;font-size:13px">Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet email.</p>'
            .'<p>L\'équipe Tinned</p>'
            .'</div>',
            htmlspecialchars($confirmUrl, ENT_QUOTES)
        );

        return $this->send($s->getEmail(), 'Confirmez votre abonnement Tinned', $html);
    }

    public function sendWelcome(Subscription $s): bool
    {
        $html =
            '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1a1a1a">'
            .'<h2>Bienvenue chez Tinned 🎉</h2>'
            .'<p>Bonjour,</p>'
            .'<p>Votre abonnement est confirmé. Nous vous tiendrons informé dès qu\'il y a du nouveau.</p>'
            .'<p>À très vite,<br>L\'équipe Tinned</p>'
            .'</div>';

        return $this->send($s->getEmail(), 'Bienvenue chez Tinned', $html);
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
