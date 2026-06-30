<?php

namespace App\Service\Shopping;

use App\Entity\Shopping\CustomerOrder;
use App\Entity\Shopping\StoreOrder;
use App\Service\Marketing\ResendMailer;

/**
 * Builds and sends the transactional emails around an order. Every method is
 * best-effort: ResendMailer never throws and no-ops without an API key, so a mail
 * problem can never break a checkout or a payment webhook.
 */
readonly class OrderMailer
{
    public function __construct(private ResendMailer $mailer) {}

    /** Sent as soon as the order is created (payment still pending). */
    public function sendOrderReceived(CustomerOrder $order): void
    {
        $email = $order->getUser()?->getEmail();
        if (!$email) {
            return;
        }

        $html = $this->shell(
            'Merci pour votre commande 🎁',
            sprintf(
                '<p>Bonjour,</p><p>Nous avons bien reçu votre commande <strong>%s</strong>. '
                .'Elle sera préparée dès la confirmation du paiement.</p>%s'
                .'<p>Total : <strong>%s</strong></p>',
                htmlspecialchars($order->getReference()),
                $this->linesTable($order),
                $this->money($order->getTotalCents(), $order->getCurrency()),
            )
        );

        $this->mailer->sendEmail($email, sprintf('Votre commande Tinned %s', $order->getReference()), $html);
    }

    /** Sent when the payment is confirmed (order moves to paid). */
    public function sendPaymentReceipt(CustomerOrder $order): void
    {
        $email = $order->getUser()?->getEmail();
        if (!$email) {
            return;
        }

        $html = $this->shell(
            'Paiement confirmé ✅',
            sprintf(
                '<p>Bonjour,</p><p>Votre paiement pour la commande <strong>%s</strong> a bien été reçu. '
                .'Les boutiques préparent votre colis.</p>%s'
                .'<p>Total payé : <strong>%s</strong></p>',
                htmlspecialchars($order->getReference()),
                $this->linesTable($order),
                $this->money($order->getTotalCents(), $order->getCurrency()),
            )
        );

        $this->mailer->sendEmail($email, sprintf('Paiement confirmé — commande %s', $order->getReference()), $html);
    }

    /** Sent to a store owner when one of their store orders becomes payable. */
    public function sendNewOrderToSeller(StoreOrder $storeOrder): void
    {
        $owner = $storeOrder->getStoreBox()?->getOwner();
        $email = $owner?->getEmail();
        if (!$email) {
            return;
        }

        $reference = $storeOrder->getCustomerOrder()?->getReference() ?? '';
        $html = $this->shell(
            'Nouvelle commande 🛎️',
            sprintf(
                '<p>Bonjour,</p><p>Vous avez reçu une nouvelle commande payée (<strong>%s</strong>) pour votre boutique '
                .'<strong>%s</strong>. Préparez l\'expédition depuis votre tableau de bord.</p>'
                .'<p>Montant boutique : <strong>%s</strong></p>',
                htmlspecialchars($reference),
                htmlspecialchars($storeOrder->getStoreBox()?->getName() ?? ''),
                $this->money($storeOrder->getSubtotalCents() + $storeOrder->getShippingCents(), $storeOrder->getCurrency()),
            )
        );

        $this->mailer->sendEmail($email, sprintf('Nouvelle commande %s', $reference), $html);
    }

    /** Sent to the buyer when a store order is shipped. */
    public function sendShipped(StoreOrder $storeOrder, ?string $trackingUrl = null): void
    {
        $order = $storeOrder->getCustomerOrder();
        $email = $order?->getUser()?->getEmail();
        if (!$email) {
            return;
        }

        $tracking = $trackingUrl
            ? sprintf('<p><a href="%s" style="display:inline-block;padding:12px 24px;background:#017E7A;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">Suivre mon colis</a></p>', htmlspecialchars($trackingUrl, ENT_QUOTES))
            : '';

        $html = $this->shell(
            'Votre colis est en route 🚚',
            sprintf(
                '<p>Bonjour,</p><p>Votre commande <strong>%s</strong> a été expédiée par <strong>%s</strong>.</p>%s',
                htmlspecialchars($order->getReference()),
                htmlspecialchars($storeOrder->getStoreBox()?->getName() ?? 'la boutique'),
                $tracking,
            )
        );

        $this->mailer->sendEmail($email, sprintf('Commande %s expédiée', $order->getReference()), $html);
    }

    private function linesTable(CustomerOrder $order): string
    {
        $rows = '';
        foreach ($order->getLines() as $line) {
            $rows .= sprintf(
                '<tr><td style="padding:6px 0">%s × %d</td><td style="padding:6px 0;text-align:right">%s</td></tr>',
                htmlspecialchars($line->getProductNameSnapshot()),
                $line->getQuantity(),
                $this->money($line->getLineTotalCents(), $order->getCurrency()),
            );
        }

        return $rows === '' ? '' : sprintf('<table style="width:100%%;border-collapse:collapse;margin:16px 0">%s</table>', $rows);
    }

    private function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' ' . ($currency === 'EUR' ? '€' : $currency);
    }

    private function shell(string $heading, string $body): string
    {
        return sprintf(
            '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1a1a1a;max-width:560px">'
            .'<h2 style="color:#0B3B3A">%s</h2>%s'
            .'<p style="color:#666;font-size:13px;margin-top:24px">L\'équipe Tinned</p>'
            .'</div>',
            htmlspecialchars($heading),
            $body,
        );
    }
}
