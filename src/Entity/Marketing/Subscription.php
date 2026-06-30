<?php

namespace App\Entity\Marketing;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\Box\Box;
use App\Entity\Product\Product;
use App\Entity\User;
use App\Processor\Marketing\SubscriptionProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'subscription')]
#[ORM\Index(name: 'IDX_SUBSCRIPTION_CONFIRM_TOKEN', columns: ['confirm_token'])]
#[ApiResource(
    normalizationContext: ['groups' => ['subscription:read']],
    denormalizationContext: ['groups' => ['subscription:write']],
    operations: [
        new Post(processor: SubscriptionProcessor::class),
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'status' => 'exact',
    'targetType' => 'exact',
    'email' => 'partial',
    'box.slug' => 'exact',
    'product.slug' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
class Subscription
{
    public const TARGET_TINNED = 'tinned';
    public const TARGET_BOX = 'box';
    public const TARGET_PRODUCT = 'product';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['subscription:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['subscription:read', 'subscription:write'])]
    private string $email = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['subscription:read'])]
    private ?User $user = null;

    #[ORM\Column(length: 12)]
    #[Assert\Choice(choices: [self::TARGET_TINNED, self::TARGET_BOX, self::TARGET_PRODUCT])]
    #[Groups(['subscription:read', 'subscription:write'])]
    private string $targetType = self::TARGET_TINNED;

    #[ORM\ManyToOne(targetEntity: Box::class)]
    #[ORM\JoinColumn(nullable: true)]
    // Box est abstraite (non-resource) : on force la résolution/écriture par IRI
    // au lieu de tenter d'instancier la classe abstraite (cf. LandingPage.box).
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['subscription:read', 'subscription:write'])]
    private ?Box $box = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['subscription:read', 'subscription:write'])]
    private ?Product $product = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['subscription:read', 'subscription:write'])]
    private bool $consentTinned = false;

    #[ORM\Column(length: 12, options: ['default' => self::STATUS_PENDING])]
    #[Groups(['subscription:read'])]
    private string $status = self::STATUS_PENDING;

    // NEVER exposed in any serialization group.
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $confirmToken = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['subscription:read'])]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    #[Groups(['subscription:read', 'subscription:write'])]
    private string $locale = 'fr';

    #[ORM\Column]
    #[Groups(['subscription:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getTargetType(): string { return $this->targetType; }
    public function setTargetType(string $targetType): static { $this->targetType = $targetType; return $this; }
    public function getBox(): ?Box { return $this->box; }
    public function setBox(?Box $box): static { $this->box = $box; return $this; }
    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): static { $this->product = $product; return $this; }
    public function isConsentTinned(): bool { return $this->consentTinned; }
    public function setConsentTinned(bool $consentTinned): static { $this->consentTinned = $consentTinned; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getConfirmToken(): ?string { return $this->confirmToken; }
    public function setConfirmToken(?string $confirmToken): static { $this->confirmToken = $confirmToken; return $this; }
    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static { $this->confirmedAt = $confirmedAt; return $this; }
    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): static { $this->locale = $locale; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    // Read-only convenience accessors for the admin listing (box/product render as IRIs otherwise).
    #[Groups(['subscription:read'])]
    public function getBoxSlug(): ?string { return $this->box?->getSlug(); }

    #[Groups(['subscription:read'])]
    public function getProductSlug(): ?string { return $this->product?->getSlug(); }
}
