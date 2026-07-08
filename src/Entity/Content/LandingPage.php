<?php

namespace App\Entity\Content;

use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Box\Box;
use App\Entity\Product\Product;
use App\Validator\ValidBlocks;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_landing_box_locale', columns: ['box_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_landing_slug_locale', columns: ['slug', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_landing_product_locale', columns: ['product_id', 'locale'])]
#[UniqueEntity(fields: ['box', 'locale'], errorPath: 'locale', message: 'Une landing page existe déjà pour cette box et cette langue.')]
#[UniqueEntity(fields: ['slug', 'locale'], errorPath: 'slug', message: 'Une landing page standalone existe déjà avec ce slug et cette langue.')]
#[UniqueEntity(fields: ['product', 'locale'], errorPath: 'locale', message: 'Une landing page existe déjà pour ce produit et cette langue.')]
#[ApiResource(
    normalizationContext: ['groups' => ['content:read']],
    denormalizationContext: ['groups' => ['content:write']],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(securityPostDenormalize: "is_granted('ROLE_ADMIN') or (object.box != null and is_granted('BOX_EDIT', object.box)) or (object.product != null and is_granted('PRODUCT_EDIT', object.product))"),
        new Patch(security: "is_granted('ROLE_ADMIN') or (object.box != null and is_granted('BOX_EDIT', object.box)) or (object.product != null and is_granted('PRODUCT_EDIT', object.product))"),
        new Delete(security: "is_granted('ROLE_ADMIN') or (object.box != null and is_granted('BOX_EDIT', object.box)) or (object.product != null and is_granted('PRODUCT_EDIT', object.product))"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['box.slug' => 'exact', 'slug' => 'exact', 'locale' => 'exact', 'product.slug' => 'exact'])]
#[ApiFilter(ExistsFilter::class, properties: ['box', 'product'])]
class LandingPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['content:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    #[Groups(['content:read', 'content:write'])]
    private string $locale = 'fr';

    #[ORM\ManyToOne(targetEntity: Box::class)]
    #[ORM\JoinColumn(nullable: true)]
    // Box est abstraite (non-resource) ; sans ceci API Platform tente d'instancier Box
    // au lieu de résoudre l'IRI -> "Cannot instantiate abstract class". On force l'IRI.
    // Optionnelle : une landing peut être rattachée à une box OU standalone (slug).
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['content:read', 'content:write'])]
    private ?Box $box = null;

    // Produit rattaché : une landing peut servir de page teaser/vitrine pour un produit
    // (rendue sur la fiche produit, comme une landing de box). Optionnelle, forcée par IRI.
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['content:read', 'content:write'])]
    private ?Product $product = null;

    // Slug d'URL pour les pages standalone (hors box). Null pour les landings de box.
    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['content:read', 'content:write'])]
    private ?string $slug = null;

    #[ORM\Column(length: 180)]
    #[Groups(['content:read', 'content:write'])]
    private string $title = '';

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['content:read', 'content:write'])]
    private ?string $metaDescription = null;

    #[ORM\Column(type: 'json')]
    #[ValidBlocks]
    #[Groups(['content:read', 'content:write'])]
    private array $blocks = [];

    public function getId(): ?int { return $this->id; }
    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): self { $this->locale = $locale; return $this; }
    public function getBox(): ?Box { return $this->box; }
    public function setBox(?Box $box): self { $this->box = $box; return $this; }
    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): self { $this->product = $product; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self { $this->slug = $slug; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $metaDescription): self { $this->metaDescription = $metaDescription; return $this; }
    public function getBlocks(): array { return $this->blocks; }
    public function setBlocks(array $blocks): self { $this->blocks = $blocks; return $this; }

    /** Product slug for the front lookup / admin listing (product renders as an IRI otherwise). */
    #[Groups(['content:read'])]
    public function getProductSlug(): ?string { return $this->product?->getSlug(); }
}
