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
use App\Processor\Box\BoxPostProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

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
#[ApiFilter(SearchFilter::class, properties: ['slug' => 'exact', 'name' => 'partial'])]
class BusinessBox extends Box
{
    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['box:read', 'box:write'])]
    private ?string $companyName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['box:read', 'box:write'])]
    private ?string $website = null;

    /** @var Collection<int, StoreBox> */
    #[ORM\OneToMany(mappedBy: 'businessBox', targetEntity: StoreBox::class)]
    private Collection $storeBoxes;

    /** @var Collection<int, BlogBox> */
    #[ORM\OneToMany(mappedBy: 'businessBox', targetEntity: BlogBox::class)]
    private Collection $blogBoxes;

    public function __construct()
    {
        parent::__construct();
        $this->storeBoxes = new ArrayCollection();
        $this->blogBoxes = new ArrayCollection();
    }

    public function getType(): string { return self::TYPE_BUSINESS; }
    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(?string $companyName): self { $this->companyName = $companyName; return $this; }
    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): self { $this->website = $website; return $this; }
    /** @return Collection<int, StoreBox> */
    public function getStoreBoxes(): Collection { return $this->storeBoxes; }
    /** @return Collection<int, BlogBox> */
    public function getBlogBoxes(): Collection { return $this->blogBoxes; }
}
