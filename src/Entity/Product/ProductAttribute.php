<?php

namespace App\Entity\Product;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'exact', 'type' => 'exact'])]
class ProductAttribute
{
    public const TYPE_SELECT = 'select';
    public const TYPE_COLOR = 'color';
    public const TYPE_NUMBER = 'number';
    public const TYPE_TEXT = 'text';
    public const TYPE_BOOLEAN = 'boolean';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Groups(['product:read', 'product:write'])]
    private string $code = '';

    #[ORM\Column(length: 120)]
    #[Groups(['product:read', 'product:write'])]
    private string $name = '';

    #[ORM\Column(length: 20)]
    #[Assert\Choice([self::TYPE_SELECT, self::TYPE_COLOR, self::TYPE_NUMBER, self::TYPE_TEXT, self::TYPE_BOOLEAN])]
    #[Groups(['product:read', 'product:write'])]
    private string $type = self::TYPE_SELECT;

    /** @var Collection<int, ProductAttributeValue> */
    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: ProductAttributeValue::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $values;

    public function __construct()
    {
        $this->values = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    /** @return Collection<int, ProductAttributeValue> */
    public function getValues(): Collection { return $this->values; }
}
