<?php

namespace App\Entity\Shopping;

use App\Entity\Box\StoreBox;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * One frozen accounting line per paid store order: gross amount, platform commission,
 * and net due to the seller. Created when the store order becomes payable and never
 * recomputed, so the seller's statement is stable regardless of later rate changes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'payout_ledger_entry')]
class PayoutLedgerEntry
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['payout:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: StoreOrder::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?StoreOrder $storeOrder = null;

    #[ORM\ManyToOne(targetEntity: StoreBox::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?StoreBox $storeBox = null;

    #[ORM\Column(length: 40)]
    #[Groups(['payout:read'])]
    private string $storeReference = '';

    #[ORM\Column]
    #[Groups(['payout:read'])]
    private int $grossCents = 0;

    #[ORM\Column]
    #[Groups(['payout:read'])]
    private int $commissionCents = 0;

    #[ORM\Column]
    #[Groups(['payout:read'])]
    private int $netCents = 0;

    #[ORM\Column]
    #[Groups(['payout:read'])]
    private int $commissionRatePercent = 0;

    #[ORM\Column(length: 16, options: ['default' => self::STATUS_PENDING])]
    #[Groups(['payout:read'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    #[Groups(['payout:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['payout:read'])]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getStoreOrder(): ?StoreOrder { return $this->storeOrder; }
    public function setStoreOrder(?StoreOrder $storeOrder): self { $this->storeOrder = $storeOrder; return $this; }
    public function getStoreBox(): ?StoreBox { return $this->storeBox; }
    public function setStoreBox(?StoreBox $storeBox): self { $this->storeBox = $storeBox; return $this; }
    public function getStoreReference(): string { return $this->storeReference; }
    public function setStoreReference(string $storeReference): self { $this->storeReference = $storeReference; return $this; }
    public function getGrossCents(): int { return $this->grossCents; }
    public function setGrossCents(int $grossCents): self { $this->grossCents = $grossCents; return $this; }
    public function getCommissionCents(): int { return $this->commissionCents; }
    public function setCommissionCents(int $commissionCents): self { $this->commissionCents = $commissionCents; return $this; }
    public function getNetCents(): int { return $this->netCents; }
    public function setNetCents(int $netCents): self { $this->netCents = $netCents; return $this; }
    public function getCommissionRatePercent(): int { return $this->commissionRatePercent; }
    public function setCommissionRatePercent(int $commissionRatePercent): self { $this->commissionRatePercent = $commissionRatePercent; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
    public function setPaidAt(?\DateTimeImmutable $paidAt): self { $this->paidAt = $paidAt; return $this; }
}
