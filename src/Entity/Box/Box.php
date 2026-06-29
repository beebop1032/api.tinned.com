<?php

namespace App\Entity\Box;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

// Resource sans opération : aucune route /api/boxes, mais Box est enregistrée comme
// resource pour que les relations typées Box (ex. LandingPage.box) résolvent l'IRI
// concret (/api/store_boxes/2 -> StoreBox) au lieu d'instancier la classe abstraite.
#[ApiResource(operations: [])]
#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'box_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'business' => BusinessBox::class,
    'store' => StoreBox::class,
    'blog' => BlogBox::class,
    'travel' => TravelBox::class,
])]
abstract class Box
{
    public const TYPE_BUSINESS = 'business';
    public const TYPE_STORE = 'store';
    public const TYPE_BLOG = 'blog';
    public const TYPE_TRAVEL = 'travel';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['box:read', 'product:read', 'article:read', 'order:read'])]
    protected ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Groups(['box:read', 'box:write', 'product:read', 'article:read', 'order:read'])]
    protected string $name = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['box:read', 'box:write', 'product:read', 'article:read', 'order:read'])]
    protected string $slug = '';

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['box:read', 'box:write'])]
    protected ?string $tagline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['box:read', 'box:write'])]
    protected ?string $description = null;

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['box:read', 'box:write', 'product:read', 'article:read'])]
    protected ?string $logoPath = null;

    #[ORM\Column(length: 280, nullable: true)]
    #[Groups(['box:read', 'box:write'])]
    protected ?string $coverPath = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['box:read', 'box:write'])]
    protected bool $active = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['box:read'])]
    protected ?User $owner = null;

    #[ORM\Column]
    #[Groups(['box:read'])]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['box:read'])]
    protected \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    abstract public function getType(): string;

    /** Le parent unique de ce box dans la hiérarchie, ou null si racine. */
    abstract public function getParentBox(): ?Box;

    #[Assert\Callback]
    public function validateNoCycle(ExecutionContextInterface $context): void
    {
        $ancestor = $this->getParentBox();
        $steps = 0;
        while ($ancestor !== null && $steps < 100) {
            if ($ancestor === $this) {
                $context->buildViolation('Un box ne peut pas être son propre ancêtre (cycle interdit).')
                    ->addViolation();
                return;
            }
            $ancestor = $ancestor->getParentBox();
            $steps++;
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getTagline(): ?string { return $this->tagline; }
    public function setTagline(?string $tagline): static { $this->tagline = $tagline; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getLogoPath(): ?string { return $this->logoPath; }
    public function setLogoPath(?string $logoPath): static { $this->logoPath = $logoPath; return $this; }
    public function getCoverPath(): ?string { return $this->coverPath; }
    public function setCoverPath(?string $coverPath): static { $this->coverPath = $coverPath; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(?User $owner): static { $this->owner = $owner; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
