<?php

namespace App\Controller;

use App\Entity\Delivery\ShippingLabel;
use App\Service\Shipping\ShippingLabelGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AdminShippingLabelController extends AbstractController
{
    #[Route('/api/admin/shipping_labels/{id}/generate', name: 'api_admin_shipping_label_generate', methods: ['POST'])]
    public function generate(ShippingLabel $label, ShippingLabelGenerator $generator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $label = $generator->generate($label);
        $status = $label->getStatus() === ShippingLabel::STATUS_ERROR ? 422 : 200;

        return $this->json($label, $status, [], ['groups' => ['shipping:read']]);
    }
}
