<?php

namespace App\Service\Pdf;

use App\Entity\Shopping\CustomerOrder;
use App\Entity\Shopping\StoreOrder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class InvoiceGenerator
{
    public function __construct(private Environment $twig) {}

    public function buyerInvoicePdf(CustomerOrder $order): string
    {
        $html = $this->twig->render('pdf/invoice_buyer.html.twig', [
            'order' => $order,
            'vat' => $this->vatSummary($order),
        ]);
        return $this->renderPdf($html);
    }

    /**
     * VAT breakdown for a VAT-inclusive (B2C) order. Line totals are grouped by their
     * snapshotted rate, shipping is taxed at the standard 21%, and any discount is
     * allocated proportionally so the total matches what was actually charged.
     *
     * @return array{byRate: array<int, array{ht: int, vat: int, ttc: int}>, totalHt: int, totalVat: int, totalTtc: int}
     */
    private function vatSummary(CustomerOrder $order): array
    {
        $ttcByRate = [];
        foreach ($order->getLines() as $line) {
            $rate = $line->getVatRatePercent();
            $ttcByRate[$rate] = ($ttcByRate[$rate] ?? 0) + $line->getLineTotalCents();
        }
        if ($order->getShippingCents() > 0) {
            $ttcByRate[21] = ($ttcByRate[21] ?? 0) + $order->getShippingCents();
        }

        $gross = array_sum($ttcByRate);
        $discount = $order->getDiscountCents();

        $byRate = [];
        $totalHt = $totalVat = $totalTtc = 0;
        foreach ($ttcByRate as $rate => $ttc) {
            $adjustedTtc = $gross > 0 ? (int) round($ttc * ($gross - $discount) / $gross) : 0;
            $ht = (int) round($adjustedTtc / (1 + $rate / 100));
            $vat = $adjustedTtc - $ht;
            $byRate[$rate] = ['ht' => $ht, 'vat' => $vat, 'ttc' => $adjustedTtc];
            $totalHt += $ht;
            $totalVat += $vat;
            $totalTtc += $adjustedTtc;
        }
        ksort($byRate);

        return ['byRate' => $byRate, 'totalHt' => $totalHt, 'totalVat' => $totalVat, 'totalTtc' => $totalTtc];
    }

    public function supplierInvoicePdf(StoreOrder $storeOrder): string
    {
        $html = $this->twig->render('pdf/invoice_supplier.html.twig', ['storeOrder' => $storeOrder]);
        return $this->renderPdf($html);
    }

    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return (string) $dompdf->output();
    }
}
