<?php

namespace App\Entity\Product;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/** One component line of a ProductBundle: a variant and a quantity. */
#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['bundle:read']],
    denormalizationContext: ['groups' => ['bundle:write']],
    operations: [
        new Post(security: "is_granted('ROLE_USER')"),
        new Delete(security: "is_granted('ROLE_USER')"),
    ],
)]
class BundleItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['bundle:read', 'cart:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductBundle::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['bundle:write'])]
    private ?ProductBundle $bundle = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['bundle:read', 'bundle:write', 'cart:read'])]
    private ?ProductVariant $variant = null;

    #[ORM\Column(options: ['default' => 1])]
    #[Groups(['bundle:read', 'bundle:write', 'cart:read'])]
    private int $quantity = 1;

    public function getId(): ?int { return $this->id; }
    public function getBundle(): ?ProductBundle { return $this->bundle; }
    public function setBundle(?ProductBundle $bundle): self { $this->bundle = $bundle; return $this; }
    public function getVariant(): ?ProductVariant { return $this->variant; }
    public function setVariant(?ProductVariant $variant): self { $this->variant = $variant; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = max(1, $quantity); return $this; }
}
