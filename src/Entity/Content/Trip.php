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
use App\Entity\Box\TravelBox;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['trip:read']],
    denormalizationContext: ['groups' => ['trip:write']],
    paginationItemsPerPage: 12,
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('TRIP_EDIT', object)"),
        new Delete(security: "is_granted('TRIP_EDIT', object)"),
    ],
)]
#[ApiFilter(BooleanFilter::class, properties: ['published'])]
#[ApiFilter(SearchFilter::class, properties: ['slug' => 'exact', 'travelBox.slug' => 'exact', 'locale' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['publishedAt'])]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['trip:read', 'box:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TravelBox::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['trip:read', 'trip:write'])]
    private ?TravelBox $travelBox = null;

    #[ORM\Column(length: 180)]
    #[Groups(['trip:read', 'trip:write', 'box:read'])]
    private string $title = '';

    #[ORM\Column(length: 200)]
    #[Groups(['trip:read', 'trip:write', 'box:read'])]
    private string $slug = '';

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    #[Groups(['trip:read', 'trip:write'])]
    private string $locale = 'fr';

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['trip:read', 'trip:write', 'box:read'])]
    private ?string $excerpt = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['trip:read', 'trip:write'])]
    private string $body = '';

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['trip:read', 'trip:write', 'box:read'])]
    private ?string $imagePath = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['trip:read', 'trip:write'])]
    private bool $published = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['trip:read', 'trip:write', 'box:read'])]
    private ?\DateTimeImmutable $publishedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getTravelBox(): ?TravelBox { return $this->travelBox; }
    public function setTravelBox(?TravelBox $travelBox): static { $this->travelBox = $travelBox; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): static { $this->locale = $locale; return $this; }
    public function getExcerpt(): ?string { return $this->excerpt; }
    public function setExcerpt(?string $excerpt): static { $this->excerpt = $excerpt; return $this; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $body): static { $this->body = $body; return $this; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): static { $this->imagePath = $imagePath; return $this; }
    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $published): static { $this->published = $published; return $this; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static { $this->publishedAt = $publishedAt; return $this; }
}
