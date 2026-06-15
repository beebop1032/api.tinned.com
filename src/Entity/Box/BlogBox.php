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
use App\Entity\Content\Article;
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
#[ApiFilter(SearchFilter::class, properties: ['slug' => 'exact', 'name' => 'partial', 'businessBox.slug' => 'exact', 'storeBox.slug' => 'exact'])]
class BlogBox extends Box
{
    #[ORM\ManyToOne(targetEntity: BusinessBox::class, inversedBy: 'blogBoxes')]
    #[Groups(['box:read', 'box:write', 'article:read'])]
    private ?BusinessBox $businessBox = null;

    #[ORM\ManyToOne(targetEntity: StoreBox::class)]
    #[Groups(['box:read', 'box:write', 'article:read'])]
    private ?StoreBox $storeBox = null;

    /** @var Collection<int, Article> */
    #[ORM\OneToMany(mappedBy: 'blogBox', targetEntity: Article::class)]
    private Collection $articles;

    public function __construct()
    {
        parent::__construct();
        $this->articles = new ArrayCollection();
    }

    public function getType(): string { return self::TYPE_BLOG; }
    public function getBusinessBox(): ?BusinessBox { return $this->businessBox; }
    public function setBusinessBox(?BusinessBox $businessBox): self { $this->businessBox = $businessBox; return $this; }
    public function getStoreBox(): ?StoreBox { return $this->storeBox; }
    public function setStoreBox(?StoreBox $storeBox): self { $this->storeBox = $storeBox; return $this; }
    /** @return Collection<int, Article> */
    public function getArticles(): Collection { return $this->articles; }
}
