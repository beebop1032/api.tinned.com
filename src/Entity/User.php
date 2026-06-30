<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_OAUTH_ACCOUNT', fields: ['oauthProvider', 'oauthId'])]
#[ORM\Index(name: 'IDX_USER_PASSWORD_RESET_TOKEN', columns: ['password_reset_token_hash'])]
#[ApiResource(
    normalizationContext: ['groups' => ['account:read']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
            denormalizationContext: ['groups' => ['account:write']],
        ),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['email' => 'partial'])]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['account:read', 'box:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['account:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['account:read', 'account:write'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['account:read', 'account:write'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['account:read', 'account:write'])]
    private ?string $phone = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    #[Groups(['account:read', 'account:write'])]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $oauthProvider = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $oauthId = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['account:read', 'account:write'])]
    private bool $active = true;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $passwordResetTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetExpiresAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['account:read'])]
    private ?\DateTimeImmutable $termsAcceptedAt = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['account:read', 'account:write'])]
    private bool $marketingConsent = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['account:read'])]
    private ?\DateTimeImmutable $marketingConsentUpdatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getOauthProvider(): ?string
    {
        return $this->oauthProvider;
    }

    public function setOauthProvider(?string $oauthProvider): User
    {
        $this->oauthProvider = $oauthProvider;
        return $this;
    }

    public function getOauthId(): ?string
    {
        return $this->oauthId;
    }

    public function setOauthId(?string $oauthId): User
    {
        $this->oauthId = $oauthId;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): User
    {
        $this->active = $active;

        return $this;
    }

    public function getPasswordResetTokenHash(): ?string
    {
        return $this->passwordResetTokenHash;
    }

    public function setPasswordResetTokenHash(?string $passwordResetTokenHash): User
    {
        $this->passwordResetTokenHash = $passwordResetTokenHash;

        return $this;
    }

    public function getPasswordResetExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetExpiresAt;
    }

    public function setPasswordResetExpiresAt(?\DateTimeImmutable $passwordResetExpiresAt): User
    {
        $this->passwordResetExpiresAt = $passwordResetExpiresAt;

        return $this;
    }

    public function getTermsAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function setTermsAcceptedAt(?\DateTimeImmutable $termsAcceptedAt): User
    {
        $this->termsAcceptedAt = $termsAcceptedAt;

        return $this;
    }

    public function hasMarketingConsent(): bool
    {
        return $this->marketingConsent;
    }

    public function setMarketingConsent(bool $marketingConsent): User
    {
        $this->marketingConsent = $marketingConsent;

        return $this;
    }

    public function getMarketingConsentUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->marketingConsentUpdatedAt;
    }

    public function setMarketingConsentUpdatedAt(?\DateTimeImmutable $marketingConsentUpdatedAt): User
    {
        $this->marketingConsentUpdatedAt = $marketingConsentUpdatedAt;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }
}
