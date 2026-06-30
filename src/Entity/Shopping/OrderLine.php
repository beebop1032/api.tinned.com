<?php

namespace App\Entity\Shopping;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Box\StoreBox;
use App\Entity\Product\ProductVariant;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['order:read']],
    denormalizationContext: ['groups' => ['order:write']],
    security: "is_granted('ROLE_USER')",
)]
class OrderLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CustomerOrder::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:write'])]
    private ?CustomerOrder $customerOrder = null;

    #[ORM\ManyToOne(targetEntity: StoreOrder::class, inversedBy: 'lines')]
    #[Groups(['order:write'])]
    private ?StoreOrder $storeOrder = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[Groups(['order:read', 'order:write'])]
    private ?ProductVariant $variant = null;

    #[ORM\ManyToOne(targetEntity: StoreBox::class)]
    #[Groups(['order:read', 'order:write'])]
    private ?StoreBox $storeBox = null;

    #[ORM\Column(length: 180)]
    #[Groups(['order:read', 'order:write'])]
    private string $storeNameSnapshot = '';

    #[ORM\Column(length: 180)]
    #[Groups(['order:read', 'order:write'])]
    private string $productNameSnapshot = '';

    #[ORM\Column(type: 'json')]
    #[Groups(['order:read', 'order:write'])]
    private array $attributesSnapshot = [];

    #[ORM\Column]
    #[Groups(['order:read', 'order:write'])]
    private int $unitPriceCentsSnapshot = 0;

    #[ORM\Column]
    #[Groups(['order:read', 'order:write'])]
    private int $quantity = 1;

    /**
     * Units actually removed from the variant stock at checkout. Equals quantity for
     * in-stock items, but is smaller for pre-orders whose stock was clamped at 0 —
     * so restitution credits back exactly what was taken, never inflating stock.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $stockReserved = 0;

    public function getId(): ?int { return $this->id; }
    public function getStockReserved(): int { return $this->stockReserved; }
    public function setStockReserved(int $stockReserved): self { $this->stockReserved = max(0, $stockReserved); return $this; }
    public function getCustomerOrder(): ?CustomerOrder { return $this->customerOrder; }
    public function setCustomerOrder(?CustomerOrder $customerOrder): self { $this->customerOrder = $customerOrder; return $this; }
    public function getStoreOrder(): ?StoreOrder { return $this->storeOrder; }
    public function setStoreOrder(?StoreOrder $storeOrder): self { $this->storeOrder = $storeOrder; return $this; }
    public function getVariant(): ?ProductVariant { return $this->variant; }
    public function setVariant(?ProductVariant $variant): self
    {
        $this->variant = $variant;
        $product = $variant?->getProduct();
        if ($product) {
            $this->productNameSnapshot = $this->productNameSnapshot ?: $product->getName();
            $this->unitPriceCentsSnapshot = $this->unitPriceCentsSnapshot ?: $variant->getPriceCents();
            $this->storeBox = $this->storeBox ?? $product->getStoreBox();
            $this->storeNameSnapshot = $this->storeNameSnapshot ?: ($product->getStoreBox()?->getName() ?? '');
        }
        return $this;
    }
    public function getStoreBox(): ?StoreBox { return $this->storeBox; }
    public function setStoreBox(?StoreBox $storeBox): self { $this->storeBox = $storeBox; return $this; }
    public function getStoreNameSnapshot(): string { return $this->storeNameSnapshot; }
    public function setStoreNameSnapshot(string $storeNameSnapshot): self { $this->storeNameSnapshot = $storeNameSnapshot; return $this; }
    public function getProductNameSnapshot(): string { return $this->productNameSnapshot; }
    public function setProductNameSnapshot(string $productNameSnapshot): self { $this->productNameSnapshot = $productNameSnapshot; return $this; }
    public function getAttributesSnapshot(): array { return $this->attributesSnapshot; }
    public function setAttributesSnapshot(array $attributesSnapshot): self { $this->attributesSnapshot = $attributesSnapshot; return $this; }
    public function getUnitPriceCentsSnapshot(): int { return $this->unitPriceCentsSnapshot; }
    public function setUnitPriceCentsSnapshot(int $unitPriceCentsSnapshot): self { $this->unitPriceCentsSnapshot = $unitPriceCentsSnapshot; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }
    #[Groups(['order:read'])]
    public function getLineTotalCents(): int { return $this->unitPriceCentsSnapshot * $this->quantity; }
}
