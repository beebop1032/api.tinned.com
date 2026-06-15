<?php

namespace App\Entity\Product;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['attribute.code' => 'exact', 'value' => 'exact'])]
class ProductAttributeValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductAttribute::class, inversedBy: 'values')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product:read', 'product:write'])]
    private ?ProductAttribute $attribute = null;

    #[ORM\Column(length: 120)]
    #[Groups(['product:read', 'product:write', 'order:read'])]
    private string $label = '';

    #[ORM\Column(length: 120)]
    #[Groups(['product:read', 'product:write', 'order:read'])]
    private string $value = '';

    #[ORM\Column(length: 7, nullable: true)]
    #[Groups(['product:read', 'product:write', 'order:read'])]
    private ?string $hexColor = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['product:read', 'product:write'])]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }
    public function getAttribute(): ?ProductAttribute { return $this->attribute; }
    public function setAttribute(?ProductAttribute $attribute): self { $this->attribute = $attribute; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }
    public function getValue(): string { return $this->value; }
    public function setValue(string $value): self { $this->value = $value; return $this; }
    public function getHexColor(): ?string { return $this->hexColor; }
    public function setHexColor(?string $hexColor): self { $this->hexColor = $hexColor; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
}
