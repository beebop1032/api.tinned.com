<?php

namespace App\Entity\Product;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Box\StoreBox;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A composable box: a fixed set of variants sold together at a bundle price
 * (either a flat price, or a percentage off the sum of the components).
 */
#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['bundle:read', 'cart:read']],
    denormalizationContext: ['groups' => ['bundle:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(securityPostDenormalize: "is_granted('BUNDLE_EDIT', object)"),
        new Patch(security: "is_granted('BUNDLE_EDIT', object)"),
        new Delete(security: "is_granted('BUNDLE_EDIT', object)"),
    ],
)]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(SearchFilter::class, properties: ['slug' => 'exact', 'storeBox.slug' => 'exact', 'storeBox.id' => 'exact'])]
class ProductBundle
{
    public const PRICING_FIXED = 'fixed';
    public const PRICING_DISCOUNT = 'discount';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['bundle:read', 'cart:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StoreBox::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['bundle:read', 'bundle:write', 'cart:read'])]
    private ?StoreBox $storeBox = null;

    #[ORM\Column(length: 180)]
    #[Groups(['bundle:read', 'bundle:write', 'cart:read'])]
    private string $name = '';

    #[ORM\Column(length: 200)]
    #[Groups(['bundle:read', 'bundle:write', 'cart:read'])]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['bundle:read', 'bundle:write'])]
    private ?string $description = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    #[Groups(['bundle:read', 'bundle:write', 'cart:read'])]
    private array $images = [];

    #[ORM\Column(length: 16, options: ['default' => self::PRICING_FIXED])]
    #[Groups(['bundle:read', 'bundle:write'])]
    private string $pricingType = self::PRICING_FIXED;

    /** Flat bundle price in cents (used when pricingType = fixed). */
    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['bundle:read', 'bundle:write'])]
    private int $fixedPriceCents = 0;

    /** Percentage off the components' price sum (used when pricingType = discount). */
    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['bundle:read', 'bundle:write'])]
    private int $discountPercent = 0;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['bundle:read', 'bundle:write'])]
    private bool $active = true;

    /** @var Collection<int, BundleItem> */
    #[ORM\OneToMany(mappedBy: 'bundle', targetEntity: BundleItem::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['bundle:read', 'bundle:write', 'cart:read'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getStoreBox(): ?StoreBox { return $this->storeBox; }
    public function setStoreBox(?StoreBox $storeBox): self { $this->storeBox = $storeBox; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    /** @return list<string> */
    public function getImages(): array { return $this->images; }
    /** @param list<string> $images */
    public function setImages(array $images): self { $this->images = $images; return $this; }
    public function getPricingType(): string { return $this->pricingType; }
    public function setPricingType(string $pricingType): self { $this->pricingType = $pricingType; return $this; }
    public function getFixedPriceCents(): int { return $this->fixedPriceCents; }
    public function setFixedPriceCents(int $fixedPriceCents): self { $this->fixedPriceCents = max(0, $fixedPriceCents); return $this; }
    public function getDiscountPercent(): int { return $this->discountPercent; }
    public function setDiscountPercent(int $discountPercent): self { $this->discountPercent = max(0, min(100, $discountPercent)); return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }
    /** @return Collection<int, BundleItem> */
    public function getItems(): Collection { return $this->items; }
    public function addItem(BundleItem $item): self { if (!$this->items->contains($item)) { $this->items->add($item); $item->setBundle($this); } return $this; }
    public function removeItem(BundleItem $item): self { if ($this->items->removeElement($item) && $item->getBundle() === $this) { $item->setBundle(null); } return $this; }

    /** Sum of the components' current variant prices, in cents. */
    #[Groups(['bundle:read', 'cart:read'])]
    public function getComponentsTotalCents(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += ($item->getVariant()?->getPriceCents() ?? 0) * $item->getQuantity();
        }
        return $total;
    }

    /** The price actually charged for one bundle, in cents. */
    #[Groups(['bundle:read', 'cart:read'])]
    public function getPriceCents(): int
    {
        if ($this->pricingType === self::PRICING_FIXED) {
            return $this->fixedPriceCents;
        }
        $components = $this->getComponentsTotalCents();
        return (int) round($components * (100 - $this->discountPercent) / 100);
    }
}
