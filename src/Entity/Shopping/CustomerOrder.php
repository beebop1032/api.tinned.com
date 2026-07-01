<?php

namespace App\Entity\Shopping;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\User;
use App\Entity\User\Address;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['order:read']],
    denormalizationContext: ['groups' => ['order:write']],
    paginationItemsPerPage: 20,
    security: "is_granted('ROLE_USER')",
)]
#[ApiFilter(SearchFilter::class, properties: ['reference' => 'exact', 'status' => 'exact', 'paymentStatus' => 'exact', 'user.email' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'totalCents'])]
class CustomerOrder
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    #[Groups(['order:read', 'order:write'])]
    private string $reference = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['order:read', 'order:write'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[Groups(['order:read', 'order:write'])]
    private ?Address $shippingAddress = null;

    /** @var Collection<int, OrderLine> */
    #[ORM\OneToMany(mappedBy: 'customerOrder', targetEntity: OrderLine::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['order:read'])]
    private Collection $lines;

    /** @var Collection<int, StoreOrder> */
    #[ORM\OneToMany(mappedBy: 'customerOrder', targetEntity: StoreOrder::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['order:read'])]
    private Collection $storeOrders;

    #[ORM\Column]
    #[Groups(['order:read', 'order:write'])]
    private int $subtotalCents = 0;

    #[ORM\Column]
    #[Groups(['order:read', 'order:write'])]
    private int $shippingCents = 0;

    #[ORM\Column(length: 40)]
    #[Assert\Choice([self::STATUS_DRAFT, self::STATUS_PENDING_PAYMENT, self::STATUS_PAID, self::STATUS_PROCESSING, self::STATUS_SHIPPED, self::STATUS_COMPLETED, self::STATUS_CANCELLED])]
    #[Groups(['order:read', 'order:write'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(length: 40, options: ['default' => 'open'])]
    #[Groups(['order:read', 'order:write'])]
    private string $paymentStatus = 'open';

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $molliePaymentId = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['order:read'])]
    private int $discountCents = 0;

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['order:read'])]
    private ?string $couponCode = null;

    /**
     * Legal sequential invoice number, assigned once when the order is paid (distinct
     * from the internal reference). Null until paid.
     */
    #[ORM\Column(length: 40, nullable: true, unique: true)]
    #[Groups(['order:read'])]
    private ?string $invoiceNumber = null;

    /**
     * True once the stock + coupon usage reserved at checkout have been given back
     * (payment failed/expired/cancelled). Guards against double restitution when the
     * Mollie webhook is replayed.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $inventoryReleased = false;

    #[ORM\Column]
    #[Groups(['order:read'])]
    private int $totalCents = 0;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    #[Groups(['order:read'])]
    private string $currency = 'EUR';

    #[ORM\Column]
    #[Groups(['order:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
        $this->storeOrders = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->reference = 'TIN-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function setReference(string $reference): self { $this->reference = $reference; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getShippingAddress(): ?Address { return $this->shippingAddress; }
    public function setShippingAddress(?Address $shippingAddress): self { $this->shippingAddress = $shippingAddress; return $this; }
    /** @return Collection<int, OrderLine> */
    public function getLines(): Collection { return $this->lines; }
    public function addLine(OrderLine $line): self { if (!$this->lines->contains($line)) { $this->lines->add($line); $line->setCustomerOrder($this); } return $this->recalculateTotals(); }
    public function removeLine(OrderLine $line): self { if ($this->lines->removeElement($line) && $line->getCustomerOrder() === $this) { $line->setCustomerOrder(null); } return $this->recalculateTotals(); }
    /** @return Collection<int, StoreOrder> */
    public function getStoreOrders(): Collection { return $this->storeOrders; }
    public function addStoreOrder(StoreOrder $storeOrder): self
    {
        if (!$this->storeOrders->contains($storeOrder)) {
            $this->storeOrders->add($storeOrder);
            $storeOrder->setCustomerOrder($this);
        }
        return $this->recalculateTotals();
    }
    public function removeStoreOrder(StoreOrder $storeOrder): self
    {
        if ($this->storeOrders->removeElement($storeOrder) && $storeOrder->getCustomerOrder() === $this) {
            $storeOrder->setCustomerOrder(null);
        }
        return $this->recalculateTotals();
    }
    public function getSubtotalCents(): int { return $this->subtotalCents; }
    public function setSubtotalCents(int $subtotalCents): self { $this->subtotalCents = max(0, $subtotalCents); $this->totalCents = $this->computeTotal(); return $this; }
    public function getShippingCents(): int { return $this->shippingCents; }
    public function setShippingCents(int $shippingCents): self { $this->shippingCents = max(0, $shippingCents); $this->totalCents = $this->computeTotal(); return $this; }
    public function getDiscountCents(): int { return $this->discountCents; }
    public function setDiscountCents(int $discountCents): self { $this->discountCents = max(0, $discountCents); $this->totalCents = $this->computeTotal(); return $this; }
    public function getCouponCode(): ?string { return $this->couponCode; }
    public function setCouponCode(?string $couponCode): self { $this->couponCode = $couponCode; return $this; }
    public function isInventoryReleased(): bool { return $this->inventoryReleased; }
    public function setInventoryReleased(bool $inventoryReleased): self { $this->inventoryReleased = $inventoryReleased; return $this; }
    public function getInvoiceNumber(): ?string { return $this->invoiceNumber; }
    public function setInvoiceNumber(?string $invoiceNumber): self { $this->invoiceNumber = $invoiceNumber; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function setPaymentStatus(string $paymentStatus): self { $this->paymentStatus = $paymentStatus; return $this; }
    public function getPaymentMethod(): ?string { return $this->paymentMethod; }
    public function setPaymentMethod(?string $paymentMethod): self { $this->paymentMethod = $paymentMethod; return $this; }
    public function getMolliePaymentId(): ?string { return $this->molliePaymentId; }
    public function setMolliePaymentId(?string $molliePaymentId): self { $this->molliePaymentId = $molliePaymentId; return $this; }
    public function getTotalCents(): int { return $this->totalCents; }
    public function setTotalCents(int $totalCents): self { $this->totalCents = $totalCents; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): self { $this->currency = $currency; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function recalculateTotals(): self
    {
        $this->subtotalCents = 0;
        if (!$this->storeOrders->isEmpty()) {
            $this->shippingCents = 0;
            foreach ($this->storeOrders as $storeOrder) {
                $storeOrder->recalculateTotals();
                $this->subtotalCents += $storeOrder->getSubtotalCents();
                $this->shippingCents += $storeOrder->getShippingCents();
            }
        } else {
            foreach ($this->lines as $line) {
                $this->subtotalCents += $line->getLineTotalCents();
            }
        }
        $this->totalCents = $this->computeTotal();
        return $this;
    }

    private function computeTotal(): int
    {
        return max(0, $this->subtotalCents - $this->discountCents) + $this->shippingCents;
    }
}
