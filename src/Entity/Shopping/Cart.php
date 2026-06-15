<?php

namespace App\Entity\Shopping;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['cart:read']],
    denormalizationContext: ['groups' => ['cart:write']],
    security: "is_granted('ROLE_USER')",
)]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cart:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?User $user = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private string $token = '';

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    #[Groups(['cart:read', 'cart:write'])]
    private array $selectedStoreSlugs = [];

    /** @var array<string, string> */
    #[ORM\Column(type: 'json')]
    #[Groups(['cart:read', 'cart:write'])]
    private array $selectedCarrierByStore = [];

    /** @var Collection<int, CartItem> */
    #[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['cart:read'])]
    private Collection $items;

    #[ORM\Column]
    #[Groups(['cart:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
        $this->token = bin2hex(random_bytes(20));
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getToken(): string { return $this->token; }
    public function setToken(string $token): self { $this->token = $token; return $this; }
    /** @return list<string> */
    public function getSelectedStoreSlugs(): array { return $this->selectedStoreSlugs; }
    /** @param list<string> $selectedStoreSlugs */
    public function setSelectedStoreSlugs(array $selectedStoreSlugs): self
    {
        $this->selectedStoreSlugs = array_values(array_unique(array_filter($selectedStoreSlugs, static fn (mixed $slug): bool => is_string($slug) && $slug !== '')));
        return $this;
    }
    /** @return array<string, string> */
    public function getSelectedCarrierByStore(): array { return $this->selectedCarrierByStore; }
    /** @param array<string, string> $selectedCarrierByStore */
    public function setSelectedCarrierByStore(array $selectedCarrierByStore): self
    {
        $this->selectedCarrierByStore = array_filter($selectedCarrierByStore, static fn (mixed $carrierCode, mixed $storeSlug): bool => is_string($storeSlug) && $storeSlug !== '' && is_string($carrierCode) && $carrierCode !== '', ARRAY_FILTER_USE_BOTH);
        return $this;
    }
    /** @return Collection<int, CartItem> */
    public function getItems(): Collection { return $this->items; }
    public function addItem(CartItem $item): self { if (!$this->items->contains($item)) { $this->items->add($item); $item->setCart($this); } return $this->touch(); }
    public function removeItem(CartItem $item): self { if ($this->items->removeElement($item) && $item->getCart() === $this) { $item->setCart(null); } return $this->touch(); }
    /** @return list<array{storeBox: object, selected: bool, items: list<CartItem>, subtotalCents: int, currency: string}> */
    #[Groups(['cart:read'])]
    public function getStoreCarts(): array
    {
        $groups = [];
        foreach ($this->items as $item) {
            $variant = $item->getVariant();
            $product = $variant?->getProduct();
            $storeBox = $product?->getStoreBox();
            if (!$variant || !$product || !$storeBox) {
                continue;
            }

            $slug = $storeBox->getSlug();
            if (!isset($groups[$slug])) {
                $groups[$slug] = [
                    'storeBox' => $storeBox,
                    'selected' => $this->selectedStoreSlugs === [] || in_array($slug, $this->selectedStoreSlugs, true),
                    'carrierCode' => $this->selectedCarrierByStore[$slug] ?? null,
                    'items' => [],
                    'subtotalCents' => 0,
                    'currency' => $product->getCurrency(),
                ];
            }

            $groups[$slug]['items'][] = $item;
            $groups[$slug]['subtotalCents'] += $variant->getPriceCents() * $item->getQuantity();
        }

        return array_values($groups);
    }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
