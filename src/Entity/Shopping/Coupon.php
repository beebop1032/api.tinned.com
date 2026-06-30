<?php

namespace App\Entity\Shopping;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'coupon')]
#[UniqueEntity('code', message: 'Ce code promo existe déjà.')]
#[ApiResource(
    normalizationContext: ['groups' => ['coupon:read']],
    denormalizationContext: ['groups' => ['coupon:write']],
    paginationItemsPerPage: 30,
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'exact'])]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'code'])]
class Coupon
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['coupon:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    #[Groups(['coupon:read', 'coupon:write'])]
    private string $code = '';

    #[ORM\Column(length: 16)]
    #[Assert\Choice([self::TYPE_PERCENT, self::TYPE_FIXED])]
    #[Groups(['coupon:read', 'coupon:write'])]
    private string $type = self::TYPE_PERCENT;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['coupon:read', 'coupon:write'])]
    private int $value = 0;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['coupon:read', 'coupon:write'])]
    private bool $active = true;

    #[ORM\Column(nullable: true)]
    #[Groups(['coupon:read', 'coupon:write'])]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['coupon:read', 'coupon:write'])]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['coupon:read', 'coupon:write'])]
    private ?int $maxUses = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['coupon:read'])]
    private int $usedCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['coupon:read', 'coupon:write'])]
    private int $minSubtotalCents = 0;

    #[ORM\Column]
    #[Groups(['coupon:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = strtoupper(trim($code)); return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getValue(): int { return $this->value; }
    public function setValue(int $value): self { $this->value = $value; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }
    public function getValidFrom(): ?\DateTimeImmutable { return $this->validFrom; }
    public function setValidFrom(?\DateTimeImmutable $validFrom): self { $this->validFrom = $validFrom; return $this; }
    public function getValidUntil(): ?\DateTimeImmutable { return $this->validUntil; }
    public function setValidUntil(?\DateTimeImmutable $validUntil): self { $this->validUntil = $validUntil; return $this; }
    public function getMaxUses(): ?int { return $this->maxUses; }
    public function setMaxUses(?int $maxUses): self { $this->maxUses = $maxUses; return $this; }
    public function getUsedCount(): int { return $this->usedCount; }
    public function setUsedCount(int $usedCount): self { $this->usedCount = $usedCount; return $this; }
    public function incrementUsedCount(): self { $this->usedCount++; return $this; }
    public function decrementUsedCount(): self { $this->usedCount = max(0, $this->usedCount - 1); return $this; }
    public function getMinSubtotalCents(): int { return $this->minSubtotalCents; }
    public function setMinSubtotalCents(int $minSubtotalCents): self { $this->minSubtotalCents = max(0, $minSubtotalCents); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** Discount in cents applicable to the given subtotal. Never exceeds the subtotal. */
    public function discountFor(int $subtotalCents): int
    {
        $subtotalCents = max(0, $subtotalCents);
        if ($this->type === self::TYPE_PERCENT) {
            $percent = max(0, min(100, $this->value));
            return min($subtotalCents, (int) round($subtotalCents * $percent / 100));
        }

        return min(max(0, $this->value), $subtotalCents);
    }

    /** True when the coupon is active, within its date window and below its usage cap. */
    public function isValidNow(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = new \DateTimeImmutable();
        if ($this->validFrom !== null && $now < $this->validFrom) {
            return false;
        }
        if ($this->validUntil !== null && $now > $this->validUntil) {
            return false;
        }

        return $this->maxUses === null || $this->usedCount < $this->maxUses;
    }
}
