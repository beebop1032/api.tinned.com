<?php

namespace App\Entity\Review;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Product\Product;
use App\Entity\User;
use App\Processor\Review\ReviewModerationProcessor;
use App\Processor\Review\ReviewProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A customer product review (reviews.io-style): star rating + written opinion, a
 * "verified purchase" badge computed from the buyer's paid orders, and admin moderation.
 * Only approved reviews are exposed to the public (see ReviewApprovedExtension); the
 * approved set drives Product::ratingAverage for on-page stars and SEO rich snippets.
 */
#[ORM\Entity]
#[ORM\Table(name: 'review')]
#[ORM\Index(name: 'idx_review_product_status', columns: ['product_id', 'status'])]
#[ApiResource(
    normalizationContext: ['groups' => ['review:read']],
    denormalizationContext: ['groups' => ['review:write']],
    paginationItemsPerPage: 20,
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_USER')", processor: ReviewProcessor::class),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['review:admin']],
            processor: ReviewModerationProcessor::class,
        ),
        new Delete(security: "is_granted('ROLE_ADMIN')", processor: ReviewModerationProcessor::class),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'product.slug' => 'exact',
    'product.storeBox.slug' => 'exact',
    'status' => 'exact',
    'verifiedPurchase' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'rating'])]
class Review
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['review:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Assert\NotNull]
    #[Groups(['review:read', 'review:write'])]
    private ?Product $product = null;

    /** Author account. Set from the authenticated user, never trusted from the payload. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['review:admin'])]
    private ?User $user = null;

    #[ORM\Column(length: 80)]
    #[Groups(['review:read', 'review:write'])]
    private string $authorName = '';

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 1, max: 5)]
    #[Groups(['review:read', 'review:write'])]
    private int $rating = 5;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Groups(['review:read', 'review:write'])]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 4000)]
    #[Groups(['review:read', 'review:write'])]
    private string $body = '';

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['review:read'])]
    private bool $verifiedPurchase = false;

    #[ORM\Column(length: 12, options: ['default' => self::STATUS_PENDING])]
    #[Assert\Choice(choices: self::STATUSES)]
    #[Groups(['review:read', 'review:admin'])]
    private string $status = self::STATUS_PENDING;

    /** Optional public reply from the seller/admin. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['review:read', 'review:admin'])]
    private ?string $merchantResponse = null;

    #[ORM\Column]
    #[Groups(['review:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): self { $this->product = $product; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getAuthorName(): string { return $this->authorName; }
    public function setAuthorName(string $authorName): self { $this->authorName = $authorName; return $this; }
    public function getRating(): int { return $this->rating; }
    public function setRating(int $rating): self { $this->rating = max(1, min(5, $rating)); return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): self { $this->title = $title; return $this; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $body): self { $this->body = $body; return $this; }
    public function isVerifiedPurchase(): bool { return $this->verifiedPurchase; }
    public function setVerifiedPurchase(bool $verifiedPurchase): self { $this->verifiedPurchase = $verifiedPurchase; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = in_array($status, self::STATUSES, true) ? $status : self::STATUS_PENDING; return $this; }
    public function getMerchantResponse(): ?string { return $this->merchantResponse; }
    public function setMerchantResponse(?string $merchantResponse): self { $this->merchantResponse = $merchantResponse; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** Product slug for the admin listing (product renders as an IRI otherwise). */
    #[Groups(['review:read'])]
    public function getProductSlug(): ?string { return $this->product?->getSlug(); }

    #[Groups(['review:read'])]
    public function getProductName(): ?string { return $this->product?->getName(); }
}
