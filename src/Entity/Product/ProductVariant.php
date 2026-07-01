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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(securityPostDenormalize: "is_granted('VARIANT_EDIT', object)"),
        new Patch(security: "is_granted('VARIANT_EDIT', object)"),
        new Delete(security: "is_granted('VARIANT_EDIT', object)"),
    ],
)]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(SearchFilter::class, properties: ['sku' => 'exact', 'product.slug' => 'exact', 'product.storeBox.slug' => 'exact', 'attributeValues.value' => 'exact'])]
class ProductVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read', 'cart:read', 'order:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product:write', 'cart:read', 'order:read'])]
    private ?Product $product = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['product:read', 'product:write', 'cart:read', 'order:read'])]
    private string $sku = '';

    #[ORM\Column]
    #[Groups(['product:read', 'product:write', 'cart:read', 'order:read'])]
    private int $priceCents = 0;

    /**
     * Optional "was" price, strictly higher than priceCents, used only to render a
     * struck-through reference price and a discount badge. Never affects the price
     * actually charged (priceCents remains the source of truth at checkout).
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['product:read', 'product:write', 'cart:read'])]
    private ?int $compareAtPriceCents = null;

    #[ORM\Column]
    #[Groups(['product:read', 'product:write', 'cart:read'])]
    private int $stock = 0;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['product:read', 'product:write'])]
    private bool $active = true;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    #[Groups(['product:read', 'product:write'])]
    private array $images = [];

    /** @var Collection<int, ProductAttributeValue> */
    #[ORM\ManyToMany(targetEntity: ProductAttributeValue::class)]
    #[ORM\JoinTable(name: 'product_variant_attribute_value')]
    #[Groups(['product:read', 'product:write', 'cart:read', 'order:read'])]
    private Collection $attributeValues;

    public function __construct()
    {
        $this->attributeValues = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): self { $this->product = $product; return $this; }
    public function getSku(): string { return $this->sku; }
    public function setSku(string $sku): self { $this->sku = $sku; return $this; }
    public function getPriceCents(): int { return $this->priceCents; }
    public function setPriceCents(int $priceCents): self { $this->priceCents = $priceCents; return $this; }
    public function getCompareAtPriceCents(): ?int { return $this->compareAtPriceCents; }
    public function setCompareAtPriceCents(?int $compareAtPriceCents): self { $this->compareAtPriceCents = $compareAtPriceCents; return $this; }
    public function getStock(): int { return $this->stock; }
    public function setStock(int $stock): self { $this->stock = $stock; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }
    /** @return list<string> */
    public function getImages(): array { return $this->images; }
    /** @param list<string> $images */
    public function setImages(array $images): self { $this->images = $images; return $this; }
    /** @return Collection<int, ProductAttributeValue> */
    public function getAttributeValues(): Collection { return $this->attributeValues; }
    public function addAttributeValue(ProductAttributeValue $value): self { if (!$this->attributeValues->contains($value)) { $this->attributeValues->add($value); } return $this; }
}
