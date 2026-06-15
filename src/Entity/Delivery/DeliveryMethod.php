<?php

namespace App\Entity\Delivery;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_delivery_method_country_code', columns: ['country_code', 'code'])]
#[ApiResource(
    normalizationContext: ['groups' => ['delivery:read']],
    denormalizationContext: ['groups' => ['delivery:write']],
    paginationEnabled: false,
)]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(SearchFilter::class, properties: ['countryCode' => 'exact', 'provider' => 'exact', 'method' => 'exact', 'code' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['position'])]
class DeliveryMethod
{
    public const METHOD_HOME = 'at_home';
    public const METHOD_RELAY = 'relay';
    public const METHOD_LOCKER = 'parcel_locker';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['delivery:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Groups(['delivery:read', 'delivery:write'])]
    private string $code = '';

    #[ORM\Column(length: 40)]
    #[Groups(['delivery:read', 'delivery:write'])]
    private string $provider = '';

    #[ORM\Column(length: 40)]
    #[Groups(['delivery:read', 'delivery:write'])]
    private string $method = self::METHOD_HOME;

    #[ORM\Column(length: 120)]
    #[Groups(['delivery:read', 'delivery:write'])]
    private string $name = '';

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['delivery:read', 'delivery:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 2)]
    #[Groups(['delivery:read', 'delivery:write'])]
    private string $countryCode = 'BE';

    #[ORM\Column]
    #[Groups(['delivery:read', 'delivery:write'])]
    private int $deliveryDaysMin = 2;

    #[ORM\Column]
    #[Groups(['delivery:read', 'delivery:write'])]
    private int $deliveryDaysMax = 4;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['delivery:read', 'delivery:write'])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['delivery:read', 'delivery:write'])]
    private bool $recommended = false;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['delivery:read', 'delivery:write'])]
    private bool $active = true;

    /** @var Collection<int, DeliveryPrice> */
    #[ORM\OneToMany(mappedBy: 'deliveryMethod', targetEntity: DeliveryPrice::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['orderPriceCents' => 'ASC'])]
    #[Groups(['delivery:read', 'delivery:write'])]
    private Collection $prices;

    public function __construct()
    {
        $this->prices = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }
    public function getProvider(): string { return $this->provider; }
    public function setProvider(string $provider): self { $this->provider = $provider; return $this; }
    public function getMethod(): string { return $this->method; }
    public function setMethod(string $method): self { $this->method = $method; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getCountryCode(): string { return $this->countryCode; }
    public function setCountryCode(string $countryCode): self { $this->countryCode = strtoupper($countryCode); return $this; }
    public function getDeliveryDaysMin(): int { return $this->deliveryDaysMin; }
    public function setDeliveryDaysMin(int $days): self { $this->deliveryDaysMin = max(0, $days); return $this; }
    public function getDeliveryDaysMax(): int { return $this->deliveryDaysMax; }
    public function setDeliveryDaysMax(int $days): self { $this->deliveryDaysMax = max($this->deliveryDaysMin, $days); return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
    public function isRecommended(): bool { return $this->recommended; }
    public function setRecommended(bool $recommended): self { $this->recommended = $recommended; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }
    /** @return Collection<int, DeliveryPrice> */
    public function getPrices(): Collection { return $this->prices; }
    public function addPrice(DeliveryPrice $price): self
    {
        if (!$this->prices->contains($price)) {
            $this->prices->add($price);
            $price->setDeliveryMethod($this);
        }
        return $this;
    }
    public function removePrice(DeliveryPrice $price): self
    {
        if ($this->prices->removeElement($price) && $price->getDeliveryMethod() === $this) {
            $price->setDeliveryMethod(null);
        }
        return $this;
    }
    public function priceFor(int $subtotalCents): int
    {
        $selectedPrice = null;
        foreach ($this->prices as $price) {
            if ($subtotalCents >= $price->getOrderPriceCents()) {
                $selectedPrice = $price->getPriceCents();
            }
        }
        return $selectedPrice ?? 0;
    }
}
