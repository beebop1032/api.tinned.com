<?php

namespace App\Entity\Content;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\Box\Box;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_landing_box_slug_locale', columns: ['box_id', 'slug', 'locale'])]
#[UniqueEntity(fields: ['box', 'slug', 'locale'], errorPath: 'slug', message: 'Une landing page avec ce slug existe déjà pour cette box et cette langue.')]
#[ApiResource(
    normalizationContext: ['groups' => ['content:read']],
    denormalizationContext: ['groups' => ['content:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['slug' => 'exact', 'box.slug' => 'exact', 'locale' => 'exact'])]
class LandingPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['content:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Groups(['content:read', 'content:write'])]
    private string $slug = '';

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    #[Groups(['content:read', 'content:write'])]
    private string $locale = 'fr';

    #[ORM\ManyToOne(targetEntity: Box::class)]
    #[Groups(['content:read', 'content:write'])]
    private ?Box $box = null;

    #[ORM\Column(length: 180)]
    #[Groups(['content:read', 'content:write'])]
    private string $title = '';

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['content:read', 'content:write'])]
    private ?string $metaDescription = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['content:read', 'content:write'])]
    private array $blocks = [];

    public function getId(): ?int { return $this->id; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): self { $this->locale = $locale; return $this; }
    public function getBox(): ?Box { return $this->box; }
    public function setBox(?Box $box): self { $this->box = $box; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $metaDescription): self { $this->metaDescription = $metaDescription; return $this; }
    public function getBlocks(): array { return $this->blocks; }
    public function setBlocks(array $blocks): self { $this->blocks = $blocks; return $this; }
}
