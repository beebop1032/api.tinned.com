<?php

namespace App\Entity\Box;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Product\Product;
use App\Processor\Box\BoxPostProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['box:read']],
    denormalizationContext: ['groups' => ['box:write']],
    paginationItemsPerPage: 24,
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            security: "is_granted('ROLE_USER')",
            processor: BoxPostProcessor::class,
        ),
        new Patch(security: "is_granted('BOX_EDIT', object)"),
        new Delete(security: "is_granted('BOX_EDIT', object)"),
    ],
)]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(SearchFilter::class, properties: ['slug' => 'exact', 'name' => 'partial', 'businessBox.slug' => 'exact'])]
class StoreBox extends Box
{
    #[ORM\ManyToOne(targetEntity: BusinessBox::class, inversedBy: 'storeBoxes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Une StoreBox doit être rattachée à une BusinessBox.')]
    #[Groups(['box:read', 'box:write', 'product:read'])]
    private ?BusinessBox $businessBox = null;

    /** @var Collection<int, Product> */
    #[ORM\OneToMany(mappedBy: 'storeBox', targetEntity: Product::class)]
    private Collection $products;

    /** @var Collection<int, BlogBox> */
    #[ORM\OneToMany(mappedBy: 'storeBox', targetEntity: BlogBox::class)]
    private Collection $blogBoxes;

    public function __construct()
    {
        parent::__construct();
        $this->products = new ArrayCollection();
        $this->blogBoxes = new ArrayCollection();
    }

    public function getType(): string { return self::TYPE_STORE; }
    public function getBusinessBox(): ?BusinessBox { return $this->businessBox; }
    public function setBusinessBox(?BusinessBox $businessBox): self { $this->businessBox = $businessBox; return $this; }
    /** @return Collection<int, Product> */
    public function getProducts(): Collection { return $this->products; }
    /** @return Collection<int, BlogBox> */
    public function getBlogBoxes(): Collection { return $this->blogBoxes; }

    public function getParentBox(): ?Box { return $this->businessBox; }
}
