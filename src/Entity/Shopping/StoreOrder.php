<?php

namespace App\Entity\Shopping;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Box\StoreBox;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['shipping:read']],
    denormalizationContext: ['groups' => ['shipping:write']],
    security: "is_granted('ROLE_ADMIN')",
    operations: [
        new \ApiPlatform\Metadata\GetCollection(),
        new \ApiPlatform\Metadata\Get(),
        new \ApiPlatform\Metadata\Patch(),
    ],
)]
class StoreOrder
{
    public const STATUS_OPEN = 'open';
    public const STATUS_WAITING_STORE = 'waiting_store';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read', 'shipping:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CustomerOrder::class, inversedBy: 'storeOrders')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:write', 'shipping:read'])]
    private ?CustomerOrder $customerOrder = null;

    #[ORM\ManyToOne(targetEntity: StoreBox::class)]
    #[Groups(['order:read', 'order:write', 'shipping:read'])]
    private ?StoreBox $storeBox = null;

    #[ORM\Column(length: 180)]
    #[Groups(['order:read', 'order:write', 'shipping:read'])]
    private string $storeNameSnapshot = '';

    #[ORM\Column(length: 40)]
    #[Assert\Choice([self::STATUS_OPEN, self::STATUS_WAITING_STORE, self::STATUS_PREPARING, self::STATUS_SHIPPED, self::STATUS_COMPLETED, self::STATUS_CANCELLED])]
    #[Groups(['order:read', 'order:write', 'shipping:read', 'shipping:write'])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['order:read', 'order:write', 'shipping:read'])]
    private ?string $carrierCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['order:read', 'order:write', 'shipping:read'])]
    private ?string $carrierNameSnapshot = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['order:read', 'order:write', 'shipping:read'])]
    private ?string $deliveryMode = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['order:read', 'shipping:read', 'shipping:write'])]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['order:read', 'shipping:read', 'shipping:write'])]
    private ?string $trackingUrl = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['order:read', 'shipping:read'])]
    private ?\DateTimeImmutable $shippedAt = null;

    /** @var Collection<int, OrderLine> */
    #[ORM\OneToMany(mappedBy: 'storeOrder', targetEntity: OrderLine::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['order:read', 'shipping:read'])]
    private Collection $lines;

    #[ORM\Column]
    #[Groups(['order:read', 'order:write', 'shipping:read'])]
    private int $subtotalCents = 0;

    #[ORM\Column]
    #[Groups(['order:read', 'order:write'])]
    private int $shippingCents = 0;

    #[ORM\Column]
    #[Groups(['order:read', 'shipping:read'])]
    private int $totalCents = 0;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    #[Groups(['order:read', 'order:write', 'shipping:read'])]
    private string $currency = 'EUR';

    #[ORM\Column]
    #[Groups(['order:read', 'shipping:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCustomerOrder(): ?CustomerOrder { return $this->customerOrder; }
    public function setCustomerOrder(?CustomerOrder $customerOrder): self { $this->customerOrder = $customerOrder; return $this; }
    public function getStoreBox(): ?StoreBox { return $this->storeBox; }
    public function setStoreBox(?StoreBox $storeBox): self
    {
        $this->storeBox = $storeBox;
        $this->storeNameSnapshot = $this->storeNameSnapshot ?: ($storeBox?->getName() ?? '');
        foreach ($this->lines as $line) {
            $line->setStoreBox($storeBox);
            $line->setStoreNameSnapshot($this->storeNameSnapshot);
        }
        return $this;
    }
    public function getStoreNameSnapshot(): string { return $this->storeNameSnapshot; }
    public function setStoreNameSnapshot(string $storeNameSnapshot): self
    {
        $this->storeNameSnapshot = $storeNameSnapshot;
        foreach ($this->lines as $line) {
            $line->setStoreNameSnapshot($storeNameSnapshot);
        }
        return $this;
    }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getCarrierCode(): ?string { return $this->carrierCode; }
    public function setCarrierCode(?string $carrierCode): self { $this->carrierCode = $carrierCode; return $this; }
    public function getCarrierNameSnapshot(): ?string { return $this->carrierNameSnapshot; }
    public function setCarrierNameSnapshot(?string $carrierNameSnapshot): self { $this->carrierNameSnapshot = $carrierNameSnapshot; return $this; }
    public function getDeliveryMode(): ?string { return $this->deliveryMode; }
    public function setDeliveryMode(?string $deliveryMode): self { $this->deliveryMode = $deliveryMode; return $this; }
    public function getTrackingNumber(): ?string { return $this->trackingNumber; }
    public function setTrackingNumber(?string $trackingNumber): self { $this->trackingNumber = $trackingNumber; return $this; }
    public function getTrackingUrl(): ?string { return $this->trackingUrl; }
    public function setTrackingUrl(?string $trackingUrl): self { $this->trackingUrl = $trackingUrl; return $this; }
    public function getShippedAt(): ?\DateTimeImmutable { return $this->shippedAt; }
    public function setShippedAt(?\DateTimeImmutable $shippedAt): self { $this->shippedAt = $shippedAt; return $this; }
    /** @return Collection<int, OrderLine> */
    public function getLines(): Collection { return $this->lines; }
    public function addLine(OrderLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setStoreOrder($this);
            $line->setStoreBox($this->storeBox);
            $line->setStoreNameSnapshot($this->storeNameSnapshot);
        }

        return $this->recalculateTotals();
    }
    public function removeLine(OrderLine $line): self
    {
        if ($this->lines->removeElement($line) && $line->getStoreOrder() === $this) {
            $line->setStoreOrder(null);
        }

        return $this->recalculateTotals();
    }
    public function getSubtotalCents(): int { return $this->subtotalCents; }
    public function setSubtotalCents(int $subtotalCents): self { $this->subtotalCents = max(0, $subtotalCents); return $this; }
    public function getShippingCents(): int { return $this->shippingCents; }
    public function setShippingCents(int $shippingCents): self { $this->shippingCents = max(0, $shippingCents); return $this->recalculateTotals(); }
    public function getTotalCents(): int { return $this->totalCents; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): self { $this->currency = $currency; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function recalculateTotals(): self
    {
        $this->subtotalCents = 0;
        foreach ($this->lines as $line) {
            $this->subtotalCents += $line->getLineTotalCents();
        }
        $this->totalCents = $this->subtotalCents + $this->shippingCents;
        return $this;
    }
}
