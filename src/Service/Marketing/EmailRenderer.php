<?php

namespace App\Service\Marketing;

use App\Entity\Marketing\Subscription;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Rendu des emails transactionnels Tinned : layout brandé (header forest + logo,
 * CTA amber, signature, footer) et copie CONTEXTUELLE selon la cible de l'abonnement
 * (produit coming-soon / box / newsletter). Tutoiement, cohérent avec le site.
 *
 * Chaque méthode renvoie ['subject' => ..., 'html' => ...].
 */
class EmailRenderer
{
    // Palette de marque (front/app/globals.css).
    private const FOREST = '#017E7A';
    private const AMBER = '#C4882A';
    private const CREAM = '#FBF5E6';
    private const INK = '#1A1208';
    private const MUTED = '#8A7E6C';
    private const BODY = '#4A4034';
    private const BORDER = '#EFE7D2';

    private const FONT_BRAND = "'Bricolage Grotesque', 'Trebuchet MS', Helvetica, Arial, sans-serif";
    private const FONT_BODY = "'Inter', -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif";

    public function __construct(
        // Le logo doit être servi à une URL absolue publique (les clients mail bloquent
        // le SVG et souvent le base64 → PNG hébergé). Sert api/public/email/logo-white.png.
        #[Autowire('%env(MAIL_ASSETS_URL)%')]
        private readonly string $assetsUrl = 'https://api.tinned.com',
    ) {
    }

    /** @return array{subject: string, html: string} */
    public function welcome(Subscription $s): array
    {
        $target = $this->targetLabel($s);

        if ($target !== null && $s->getTargetType() === Subscription::TARGET_PRODUCT) {
            $subject = sprintf('C\'est noté — on te prévient pour %s', $target);
            $title = 'C\'est noté ✓';
            $intro = sprintf(
                'On te prévient dès que <strong>%s</strong> est en ligne — un seul email, au bon moment, rien d\'autre. Tu peux fermer cet onglet l\'esprit tranquille.',
                $this->e($target),
            );
        } elseif ($target !== null && $s->getTargetType() === Subscription::TARGET_BOX) {
            $subject = sprintf('Bienvenue — tu suis %s', $target);
            $title = 'Tu es des nôtres ✨';
            $intro = sprintf('C\'est confirmé&nbsp;! Tu suis désormais <strong>%s</strong>. On t\'écrit seulement quand ça vaut le coup.', $this->e($target));
        } else {
            $subject = 'Bienvenue chez Tinned ✨';
            $title = 'Bienvenue chez Tinned ✨';
            $intro = 'C\'est confirmé&nbsp;! Tu fais maintenant partie de Tinned. On t\'écrit seulement quand ça vaut le coup : nouvelles box, coups de cœur et avant-premières.';
        }

        $body = $this->paragraph($intro)
            .$this->button('Découvrir Tinned', $this->frontUrl());

        return ['subject' => $subject, 'html' => $this->layout($title, $body, 'C\'est confirmé.')];
    }

    /**
     * Bienvenue à la création de compte : le point de confirmation unique.
     *
     * @return array{subject: string, html: string}
     */
    public function accountWelcome(string $firstName): array
    {
        $hi = trim($firstName) !== '' ? sprintf('Salut %s,', $this->e(trim($firstName))) : 'Salut,';

        $body = $this->paragraph($hi)
            .$this->paragraph('Ton compte Tinned est prêt 🎉 Suis les produits et les box qui t\'intéressent&nbsp;: on te prévient dès qu\'ils arrivent ou reviennent en stock. Un seul email par événement, jamais de spam.')
            .$this->button('Explorer les box', $this->frontUrl())
            .$this->fine('Tu peux gérer tes préférences de notifications à tout moment depuis ton compte.');

        return [
            'subject' => 'Bienvenue sur Tinned 🎉',
            'html' => $this->layout('Bienvenue sur Tinned 🎉', $body, 'Ton compte est prêt.'),
        ];
    }

    /** @return array{subject: string, html: string} */
    public function launchLive(Subscription $s, string $productName, string $url): array
    {
        $body = $this->paragraph(sprintf(
            'Tu nous avais demandé d\'être prévenu·e — ça y est&nbsp;: <strong>%s</strong> est disponible sur Tinned. Tu es parmi les premiers à le savoir.',
            $this->e($productName),
        )).$this->button('Découvrir le produit', $url);

        return [
            'subject' => sprintf('C\'est en ligne — %s est disponible 🎉', $productName),
            'html' => $this->layout('C\'est en ligne 🎉', $body, sprintf('%s est disponible.', $productName)),
        ];
    }

    /** @return array{subject: string, html: string} */
    public function backInStock(Subscription $s, string $productName, string $url): array
    {
        $body = $this->paragraph(sprintf(
            'Bonne nouvelle&nbsp;: <strong>%s</strong> est de nouveau disponible. Les stocks sont limités, ne tarde pas trop.',
            $this->e($productName),
        )).$this->button('Commander maintenant', $url);

        return [
            'subject' => sprintf('De retour en stock — %s 📦', $productName),
            'html' => $this->layout('De retour en stock 📦', $body, sprintf('%s est de nouveau dispo.', $productName)),
        ];
    }

    // ---- Cible ----------------------------------------------------------------

    private function targetLabel(Subscription $s): ?string
    {
        return match ($s->getTargetType()) {
            Subscription::TARGET_PRODUCT => $s->getProduct()?->getName(),
            Subscription::TARGET_BOX => $s->getBox()?->getName(),
            default => null,
        };
    }

    private function frontUrl(): string
    {
        // Le logo est hébergé sur l'API ; le site vit ailleurs. À défaut, on renvoie vers la home publique.
        return 'https://appbeta.tinned.com';
    }

    // ---- Briques HTML ---------------------------------------------------------

    private function paragraph(string $html): string
    {
        return sprintf(
            '<p style="margin:0 0 20px;font:400 16px/1.6 %s;color:%s">%s</p>',
            self::FONT_BODY,
            self::BODY,
            $html,
        );
    }

    private function button(string $label, string $url): string
    {
        // Table-based pour Outlook.
        return sprintf(
            '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 4px"><tr><td style="border-radius:10px;background:%s">'
            .'<a href="%s" style="display:inline-block;padding:14px 30px;font:700 15px/1 %s;color:#ffffff;text-decoration:none;border-radius:10px">%s</a>'
            .'</td></tr></table>',
            self::AMBER,
            $this->e($url),
            self::FONT_BODY,
            $this->e($label),
        );
    }

    private function fine(string $text): string
    {
        return sprintf(
            '<p style="margin:24px 0 0;font:400 13px/1.5 %s;color:%s">%s</p>',
            self::FONT_BODY,
            self::MUTED,
            $this->e($text),
        );
    }

    private function layout(string $title, string $bodyHtml, string $preheader): string
    {
        $logo = $this->e($this->assetsUrl).'/email/logo-white.png';

        return ''
            .'<!-- preheader -->'
            .sprintf('<div style="display:none;max-height:0;overflow:hidden;opacity:0">%s</div>', $this->e($preheader))
            .sprintf('<div style="background:%s;padding:32px 12px;font-family:%s">', self::CREAM, self::FONT_BODY)
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
            .sprintf('<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%%;background:#ffffff;border:1px solid %s;border-radius:14px;overflow:hidden">', self::BORDER)

            // Header
            .sprintf('<tr><td style="background:%s;padding:26px 32px">', self::FOREST)
            .'<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
            .sprintf('<td style="vertical-align:middle;padding-right:12px"><img src="%s" width="34" height="39" alt="Tinned" style="display:block;border:0"></td>', $logo)
            .sprintf('<td style="vertical-align:middle"><span style="font:700 23px/1 %s;color:#ffffff;letter-spacing:-0.02em">Tinned</span></td>', self::FONT_BRAND)
            .'</tr></table>'
            .'</td></tr>'

            // Body
            .'<tr><td style="padding:36px 32px 12px">'
            .sprintf('<h1 style="margin:0 0 16px;font:700 26px/1.2 %s;color:%s;letter-spacing:-0.02em">%s</h1>', self::FONT_BRAND, self::INK, $this->e($title))
            .$bodyHtml
            .'</td></tr>'

            // Signature
            .'<tr><td style="padding:8px 32px 32px">'
            .sprintf('<p style="margin:0;font:400 16px/1.6 %s;color:%s">À très vite,<br><strong style="color:%s">L\'équipe Tinned</strong></p>', self::FONT_BODY, self::BODY, self::INK)
            .'</td></tr>'

            // Footer
            .sprintf('<tr><td style="background:%s;padding:22px 32px;border-top:1px solid %s">', self::CREAM, self::BORDER)
            .sprintf('<p style="margin:0 0 6px;font:700 15px/1 %s;color:%s;letter-spacing:-0.02em">Tinned</p>', self::FONT_BRAND, self::FOREST)
            .sprintf('<p style="margin:0;font:400 12px/1.6 %s;color:%s">Tu reçois cet email parce que tu t\'es inscrit·e sur Tinned. Pour ne plus rien recevoir, réponds simplement « STOP » à ce message.</p>', self::FONT_BODY, self::MUTED)
            .'</td></tr>'

            .'</table>'
            .'</td></tr></table>'
            .'</div>';
    }

    private function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES);
    }
}
