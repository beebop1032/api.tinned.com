<?php

namespace App\Entity\Shopping;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Box\StoreBox;
use App\Entity\Product\ProductVariant;
use App\Entity\User;
use App\Processor\Shopping\CreateSubscriptionProcessor;
use App\Provider\Shopping\MySubscriptionsProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A recurring box subscription: one variant billed on a fixed cadence. Distinct from
 * the marketing waitlist (App\Entity\Marketing\Subscription). The Mollie mandate /
 * subscription ids are filled in when recurring billing is enabled.
 */
#[ORM\Entity]
#[ORM\Table(name: 'box_subscription')]
#[ApiResource(
    normalizationContext: ['groups' => ['subscription_box:read']],
    denormalizationContext: ['groups' => ['subscription_box:write']],
    operations: [
        new Post(
            uriTemplate: '/box_subscriptions',
            security: "is_granted('ROLE_USER')",
            processor: CreateSubscriptionProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/my_subscriptions',
            security: "is_granted('ROLE_USER')",
            provider: MySubscriptionsProvider::class,
        ),
        new Patch(
            uriTemplate: '/my_subscriptions/{id}',
            provider: MySubscriptionsProvider::class,
            security: "is_granted('ROLE_USER') and object.getUser() === user",
        ),
    ],
)]
class BoxSubscription
{
    public const FREQ_MONTHLY = 'monthly';
    public const FREQ_QUARTERLY = 'quarterly';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['subscription_box:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: StoreBox::class)]
    #[Groups(['subscription_box:read'])]
    private ?StoreBox $storeBox = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['subscription_box:read', 'subscription_box:write'])]
    private ?ProductVariant $variant = null;

    #[ORM\Column(length: 16, options: ['default' => self::FREQ_MONTHLY])]
    #[Assert\Choice([self::FREQ_MONTHLY, self::FREQ_QUARTERLY])]
    #[Groups(['subscription_box:read', 'subscription_box:write'])]
    private string $frequency = self::FREQ_MONTHLY;

    #[ORM\Column(length: 16, options: ['default' => self::STATUS_ACTIVE])]
    #[Groups(['subscription_box:read', 'subscription_box:write'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(nullable: true)]
    #[Groups(['subscription_box:read'])]
    private ?\DateTimeImmutable $nextRenewalAt = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $mollieMandateId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $mollieSubscriptionId = null;

    #[ORM\Column]
    #[Groups(['subscription_box:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getStoreBox(): ?StoreBox { return $this->storeBox; }
    public function setStoreBox(?StoreBox $storeBox): self { $this->storeBox = $storeBox; return $this; }
    public function getVariant(): ?ProductVariant { return $this->variant; }
    public function setVariant(?ProductVariant $variant): self { $this->variant = $variant; return $this; }
    public function getFrequency(): string { return $this->frequency; }
    public function setFrequency(string $frequency): self { $this->frequency = $frequency; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getNextRenewalAt(): ?\DateTimeImmutable { return $this->nextRenewalAt; }
    public function setNextRenewalAt(?\DateTimeImmutable $nextRenewalAt): self { $this->nextRenewalAt = $nextRenewalAt; return $this; }
    public function getMollieMandateId(): ?string { return $this->mollieMandateId; }
    public function setMollieMandateId(?string $mollieMandateId): self { $this->mollieMandateId = $mollieMandateId; return $this; }
    public function getMollieSubscriptionId(): ?string { return $this->mollieSubscriptionId; }
    public function setMollieSubscriptionId(?string $mollieSubscriptionId): self { $this->mollieSubscriptionId = $mollieSubscriptionId; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** Advances nextRenewalAt from the given anchor according to the frequency. */
    public function computeNextRenewal(\DateTimeImmutable $from): \DateTimeImmutable
    {
        return $from->add(new \DateInterval($this->frequency === self::FREQ_QUARTERLY ? 'P3M' : 'P1M'));
    }
}
