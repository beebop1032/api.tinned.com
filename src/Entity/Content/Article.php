<?php

namespace App\Entity\Content;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Box\BlogBox;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['article:read']],
    denormalizationContext: ['groups' => ['article:write']],
    paginationItemsPerPage: 12,
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('ARTICLE_EDIT', object)"),
        new Delete(security: "is_granted('ARTICLE_EDIT', object)"),
    ],
)]
#[ApiFilter(BooleanFilter::class, properties: ['published'])]
#[ApiFilter(SearchFilter::class, properties: ['slug' => 'exact', 'title' => 'partial', 'blogBox.slug' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['publishedAt', 'title'])]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['article:read', 'box:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BlogBox::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['article:read', 'article:write'])]
    private ?BlogBox $blogBox = null;

    #[ORM\Column(length: 180)]
    #[Groups(['article:read', 'article:write', 'box:read'])]
    private string $title = '';

    #[ORM\Column(length: 200)]
    #[Groups(['article:read', 'article:write', 'box:read'])]
    private string $slug = '';

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['article:read', 'article:write', 'box:read'])]
    private ?string $excerpt = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['article:read', 'article:write'])]
    private string $body = '';

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['article:read', 'article:write', 'box:read'])]
    private ?string $imagePath = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['article:read', 'article:write'])]
    private bool $published = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['article:read', 'article:write', 'box:read'])]
    private ?\DateTimeImmutable $publishedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getBlogBox(): ?BlogBox { return $this->blogBox; }
    public function setBlogBox(?BlogBox $blogBox): self { $this->blogBox = $blogBox; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
    public function getExcerpt(): ?string { return $this->excerpt; }
    public function setExcerpt(?string $excerpt): self { $this->excerpt = $excerpt; return $this; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $body): self { $this->body = $body; return $this; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): self { $this->imagePath = $imagePath; return $this; }
    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $published): self { $this->published = $published; return $this; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self { $this->publishedAt = $publishedAt; return $this; }
}
