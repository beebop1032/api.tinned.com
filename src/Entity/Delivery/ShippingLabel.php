<?php

namespace App\Entity\Delivery;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\Shopping\StoreOrder;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['shipping:read']],
    denormalizationContext: ['groups' => ['shipping:write']],
    security: "is_granted('ROLE_ADMIN')",
    paginationItemsPerPage: 50,
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact', 'carrierCode' => 'exact', 'storeOrder.id' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'printedAt'])]
class ShippingLabel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_PRINTED = 'printed';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['shipping:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StoreOrder::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['shipping:read', 'shipping:write'])]
    private ?StoreOrder $storeOrder = null;

    #[ORM\Column(length: 80)]
    #[Groups(['shipping:read'])]
    private string $carrierCode = '';

    #[ORM\Column(length: 120)]
    #[Groups(['shipping:read'])]
    private string $carrierName = '';

    #[ORM\Column(length: 20, options: ['default' => 'A6'])]
    #[Groups(['shipping:read', 'shipping:write'])]
    private string $format = 'A6';

    #[ORM\Column(options: ['default' => 1])]
    #[Groups(['shipping:read', 'shipping:write'])]
    private int $copies = 1;

    #[ORM\Column(options: ['default' => 1000])]
    #[Groups(['shipping:read', 'shipping:write'])]
    private int $weightGrams = 1000;

    #[ORM\Column(length: 20)]
    #[Assert\Choice([self::STATUS_PENDING, self::STATUS_READY, self::STATUS_PRINTED, self::STATUS_ERROR])]
    #[Groups(['shipping:read', 'shipping:write'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['shipping:read', 'shipping:write'])]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['shipping:read'])]
    private ?string $trackingUrl = null;

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['shipping:read'])]
    private ?string $labelUrl = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['shipping:read', 'shipping:write'])]
    private ?string $pickupPointId = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['shipping:read', 'shipping:write'])]
    private ?string $pickupPointName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['shipping:read', 'shipping:write'])]
    private ?string $pickupPointStreet = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['shipping:read', 'shipping:write'])]
    private ?string $pickupPointPostalCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['shipping:read', 'shipping:write'])]
    private ?string $pickupPointCity = null;

    #[ORM\Column(length: 2, nullable: true)]
    #[Groups(['shipping:read', 'shipping:write'])]
    private ?string $pickupPointCountryCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['shipping:read', 'shipping:write'])]
    private ?string $errorMessage = null;

    #[ORM\Column]
    #[Groups(['shipping:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['shipping:read', 'shipping:write'])]
    private ?\DateTimeImmutable $printedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getStoreOrder(): ?StoreOrder { return $this->storeOrder; }
    public function setStoreOrder(?StoreOrder $storeOrder): self
    {
        $this->storeOrder = $storeOrder;
        if ($storeOrder) {
            $this->carrierCode = $storeOrder->getCarrierCode() ?? '';
            $this->carrierName = $storeOrder->getCarrierNameSnapshot() ?? '';
        }
        return $this;
    }
    public function getCarrierCode(): string { return $this->carrierCode; }
    public function getCarrierName(): string { return $this->carrierName; }
    public function getFormat(): string { return $this->format; }
    public function setFormat(string $format): self { $this->format = $format; return $this; }
    public function getCopies(): int { return $this->copies; }
    public function setCopies(int $copies): self { $this->copies = max(1, min(20, $copies)); return $this; }
    public function getWeightGrams(): int { return $this->weightGrams; }
    public function setWeightGrams(int $weightGrams): self { $this->weightGrams = max(1, $weightGrams); return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self
    {
        $this->status = $status;
        if ($status === self::STATUS_PRINTED && !$this->printedAt) {
            $this->printedAt = new \DateTimeImmutable();
        }
        return $this;
    }
    public function getTrackingNumber(): ?string { return $this->trackingNumber; }
    public function setTrackingNumber(?string $trackingNumber): self { $this->trackingNumber = $trackingNumber; return $this; }
    public function getTrackingUrl(): ?string { return $this->trackingUrl; }
    public function setTrackingUrl(?string $trackingUrl): self { $this->trackingUrl = $trackingUrl; return $this; }
    public function getLabelUrl(): ?string { return $this->labelUrl; }
    public function setLabelUrl(?string $labelUrl): self { $this->labelUrl = $labelUrl; return $this; }
    public function getPickupPointId(): ?string { return $this->pickupPointId; }
    public function setPickupPointId(?string $id): self { $this->pickupPointId = $id; return $this; }
    public function getPickupPointName(): ?string { return $this->pickupPointName; }
    public function setPickupPointName(?string $name): self { $this->pickupPointName = $name; return $this; }
    public function getPickupPointStreet(): ?string { return $this->pickupPointStreet; }
    public function setPickupPointStreet(?string $street): self { $this->pickupPointStreet = $street; return $this; }
    public function getPickupPointPostalCode(): ?string { return $this->pickupPointPostalCode; }
    public function setPickupPointPostalCode(?string $postalCode): self { $this->pickupPointPostalCode = $postalCode; return $this; }
    public function getPickupPointCity(): ?string { return $this->pickupPointCity; }
    public function setPickupPointCity(?string $city): self { $this->pickupPointCity = $city; return $this; }
    public function getPickupPointCountryCode(): ?string { return $this->pickupPointCountryCode; }
    public function setPickupPointCountryCode(?string $countryCode): self
    {
        $this->pickupPointCountryCode = $countryCode ? strtoupper($countryCode) : null;
        return $this;
    }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $message): self { $this->errorMessage = $message; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPrintedAt(): ?\DateTimeImmutable { return $this->printedAt; }
    public function setPrintedAt(?\DateTimeImmutable $printedAt): self { $this->printedAt = $printedAt; return $this; }
}
