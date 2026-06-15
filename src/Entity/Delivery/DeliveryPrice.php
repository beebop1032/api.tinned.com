<?php

namespace App\Entity\Delivery;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
class DeliveryPrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['delivery:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeliveryMethod::class, inversedBy: 'prices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DeliveryMethod $deliveryMethod = null;

    #[ORM\Column]
    #[Groups(['delivery:read', 'delivery:write'])]
    private int $orderPriceCents = 0;

    #[ORM\Column]
    #[Groups(['delivery:read', 'delivery:write'])]
    private int $priceCents = 0;

    public function getId(): ?int { return $this->id; }
    public function getDeliveryMethod(): ?DeliveryMethod { return $this->deliveryMethod; }
    public function setDeliveryMethod(?DeliveryMethod $deliveryMethod): self { $this->deliveryMethod = $deliveryMethod; return $this; }
    public function getOrderPriceCents(): int { return $this->orderPriceCents; }
    public function setOrderPriceCents(int $cents): self { $this->orderPriceCents = max(0, $cents); return $this; }
    public function getPriceCents(): int { return $this->priceCents; }
    public function setPriceCents(int $cents): self { $this->priceCents = max(0, $cents); return $this; }
}
