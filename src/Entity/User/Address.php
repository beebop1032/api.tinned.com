<?php

namespace App\Entity\User;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['user:read', 'order:read']],
    denormalizationContext: ['groups' => ['user:write']],
    security: "is_granted('ROLE_ADMIN')",
)]
#[ApiFilter(SearchFilter::class, properties: ['user.email' => 'exact', 'countryCode' => 'exact'])]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'order:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['user:read', 'user:write'])]
    private ?User $user = null;

    #[ORM\Column(length: 120)]
    #[Groups(['user:read', 'user:write', 'order:read'])]
    private string $firstName = '';

    #[ORM\Column(length: 120)]
    #[Groups(['user:read', 'user:write', 'order:read'])]
    private string $lastName = '';

    #[ORM\Column(length: 180)]
    #[Groups(['user:read', 'user:write', 'order:read'])]
    private string $street = '';

    #[ORM\Column(length: 20)]
    #[Groups(['user:read', 'user:write', 'order:read'])]
    private string $postalCode = '';

    #[ORM\Column(length: 120)]
    #[Groups(['user:read', 'user:write', 'order:read'])]
    private string $city = '';

    #[ORM\Column(length: 2)]
    #[Groups(['user:read', 'user:write', 'order:read'])]
    private string $countryCode = 'BE';

    #[ORM\Column(length: 40, nullable: true)]
    #[Groups(['user:read', 'user:write', 'order:read'])]
    private ?string $phone = null;

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }
    public function getStreet(): string { return $this->street; }
    public function setStreet(string $street): self { $this->street = $street; return $this; }
    public function getPostalCode(): string { return $this->postalCode; }
    public function setPostalCode(string $postalCode): self { $this->postalCode = $postalCode; return $this; }
    public function getCity(): string { return $this->city; }
    public function setCity(string $city): self { $this->city = $city; return $this; }
    public function getCountryCode(): string { return $this->countryCode; }
    public function setCountryCode(string $countryCode): self { $this->countryCode = $countryCode; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }
}
