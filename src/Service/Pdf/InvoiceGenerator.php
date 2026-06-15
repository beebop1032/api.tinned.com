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
        $html = $this->twig->render('pdf/invoice_buyer.html.twig', ['order' => $order]);
        return $this->renderPdf($html);
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
