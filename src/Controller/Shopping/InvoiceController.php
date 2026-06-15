<?php

namespace App\Controller\Shopping;

use App\Entity\Shopping\CustomerOrder;
use App\Entity\Shopping\StoreOrder;
use App\Entity\User;
use App\Service\Pdf\InvoiceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class InvoiceController extends AbstractController
{
    public function __construct(
        private InvoiceGenerator $generator,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/api/my_orders/{id}/invoice', name: 'buyer_invoice', methods: ['GET'])]
    public function buyerInvoice(int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $order = $this->em->find(CustomerOrder::class, $id);
        if (!$order || $order->getUser()?->getId() !== $user->getId()) {
            throw new NotFoundHttpException();
        }

        $pdf = $this->generator->buyerInvoicePdf($order);
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="tinned-invoice-' . $order->getReference() . '.pdf"',
        ]);
    }

    #[Route('/api/my_store_orders/{id}/supplier-invoice', name: 'supplier_invoice', methods: ['GET'])]
    public function supplierInvoice(int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $storeOrder = $this->em->find(StoreOrder::class, $id);
        if (!$storeOrder) {
            throw new NotFoundHttpException();
        }

        $box = $storeOrder->getStoreBox();
        if ($box?->getOwner()?->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException();
        }

        $pdf = $this->generator->supplierInvoicePdf($storeOrder);
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="tinned-supplier-invoice-' . $storeOrder->getId() . '.pdf"',
        ]);
    }
}
