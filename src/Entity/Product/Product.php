<?php

namespace App\Entity\Product;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_product_box_slug', columns: ['store_box_id', 'slug'])]
#[UniqueEntity(fields: ['storeBox', 'slug'], errorPath: 'slug', message: 'Un produit avec ce slug existe déjà dans cette boutique.')]
#[ApiResource(
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
    paginationItemsPerPage: 24,
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('PRODUCT_EDIT', object)"),
        new Delete(security: "is_granted('PRODUCT_EDIT', object)"),
    ],
)]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(SearchFilter::class, properties: ['slug' => 'exact', 'name' => 'partial', 'storeBox.slug' => 'exact', 'availability' => 'exact'])]
#[ApiFilter(RangeFilter::class, properties: ['basePriceCents'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'basePriceCents', 'name'])]
class Product
{
    public const AVAILABILITIES = ['available', 'coming_soon', 'preorder'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read', 'box:read', 'cart:read', 'order:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StoreBox::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product:read', 'product:write', 'cart:read', 'order:read'])]
    private ?StoreBox $storeBox = null;

    #[ORM\Column(length: 180)]
    #[Groups(['product:read', 'product:write', 'box:read', 'cart:read', 'order:read'])]
    private string $name = '';

    #[ORM\Column(length: 200)]
    #[Groups(['product:read', 'product:write', 'box:read', 'cart:read', 'order:read'])]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['product:read', 'product:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['product:read', 'product:write', 'box:read'])]
    private int $basePriceCents = 0;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    #[Groups(['product:read', 'product:write', 'box:read', 'cart:read', 'order:read'])]
    private string $currency = 'EUR';

    /** Belgian VAT rate applicable to this product (21 standard, 12, 6, or 0). Prices are VAT-inclusive. */
    #[ORM\Column(options: ['default' => 21])]
    #[Groups(['product:read', 'product:write'])]
    private int $vatRatePercent = 21;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['product:read', 'product:write', 'box:read'])]
    private bool $active = true;

    #[ORM\Column(length: 16, options: ['default' => 'available'])]
    #[Groups(['product:read', 'product:write', 'box:read', 'cart:read', 'order:read'])]
    private string $availability = 'available';

    #[ORM\Column(nullable: true)]
    #[Groups(['product:read', 'product:write', 'box:read'])]
    private ?\DateTimeImmutable $releaseAt = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    #[Groups(['product:read', 'product:write', 'box:read'])]
    private array $images = [];

    /** @var Collection<int, ProductVariant> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['product:read'])]
    private Collection $variants;

    #[ORM\Column]
    #[Groups(['product:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->variants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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
    public function getBasePriceCents(): int { return $this->basePriceCents; }
    public function setBasePriceCents(int $basePriceCents): self { $this->basePriceCents = $basePriceCents; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): self { $this->currency = $currency; return $this; }
    public function getVatRatePercent(): int { return $this->vatRatePercent; }
    public function setVatRatePercent(int $vatRatePercent): self { $this->vatRatePercent = max(0, min(100, $vatRatePercent)); return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }
    public function getAvailability(): string { return $this->availability; }
    public function setAvailability(string $availability): self { $this->availability = in_array($availability, self::AVAILABILITIES, true) ? $availability : 'available'; return $this; }
    public function getReleaseAt(): ?\DateTimeImmutable { return $this->releaseAt; }
    public function setReleaseAt(?\DateTimeImmutable $releaseAt): self { $this->releaseAt = $releaseAt; return $this; }
    public function isPurchasable(): bool { return $this->availability !== 'coming_soon'; }
    /** @return list<string> */
    public function getImages(): array { return $this->images; }
    /** @param list<string> $images */
    public function setImages(array $images): self { $this->images = $images; return $this; }
    /** @return Collection<int, ProductVariant> */
    public function getVariants(): Collection { return $this->variants; }
    public function addVariant(ProductVariant $variant): self { if (!$this->variants->contains($variant)) { $this->variants->add($variant); $variant->setProduct($this); } return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
