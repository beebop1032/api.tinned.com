<?php

namespace App\Entity\Product;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Processor\Product\StockMovementProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['stock:read']],
    denormalizationContext: ['groups' => ['stock:write']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')", processor: StockMovementProcessor::class),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['variant.id' => 'exact', 'reason' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
class StockMovement
{
    public const REASONS = ['sale', 'restock', 'adjustment', 'return', 'initial'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['stock:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['stock:read', 'stock:write'])]
    private ?ProductVariant $variant = null;

    #[ORM\Column]
    #[Groups(['stock:read', 'stock:write'])]
    private int $delta = 0;

    #[ORM\Column(length: 20)]
    #[Groups(['stock:read', 'stock:write'])]
    private string $reason = 'adjustment';

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['stock:read', 'stock:write'])]
    private ?string $note = null;

    #[ORM\Column]
    #[Groups(['stock:read'])]
    private int $resultingStock = 0;

    #[ORM\Column]
    #[Groups(['stock:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVariant(): ?ProductVariant { return $this->variant; }
    public function setVariant(?ProductVariant $variant): self { $this->variant = $variant; return $this; }
    public function getDelta(): int { return $this->delta; }
    public function setDelta(int $delta): self { $this->delta = $delta; return $this; }
    public function getReason(): string { return $this->reason; }
    public function setReason(string $reason): self { $this->reason = $reason; return $this; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): self { $this->note = $note; return $this; }
    public function getResultingStock(): int { return $this->resultingStock; }
    public function setResultingStock(int $resultingStock): self { $this->resultingStock = $resultingStock; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
