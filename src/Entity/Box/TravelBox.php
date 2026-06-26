<?php

namespace App\Entity\Box;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Content\Trip;
use App\Processor\Box\BoxPostProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['box:read']],
    denormalizationContext: ['groups' => ['box:write']],
    paginationItemsPerPage: 24,
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            security: "is_granted('ROLE_USER')",
            processor: BoxPostProcessor::class,
        ),
        new Patch(security: "is_granted('BOX_EDIT', object)"),
        new Delete(security: "is_granted('BOX_EDIT', object)"),
    ],
)]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(SearchFilter::class, properties: ['slug' => 'exact', 'name' => 'partial'])]
class TravelBox extends Box
{
    #[ORM\ManyToOne(targetEntity: TravelBox::class, inversedBy: 'childTravelBoxes')]
    #[Groups(['box:read', 'box:write'])]
    private ?TravelBox $parentTravelBox = null;

    #[ORM\ManyToOne(targetEntity: BusinessBox::class, inversedBy: 'travelBoxes')]
    #[Groups(['box:read', 'box:write'])]
    private ?BusinessBox $businessBox = null;

    /** @var Collection<int, Trip> */
    #[ORM\OneToMany(mappedBy: 'travelBox', targetEntity: Trip::class)]
    #[Groups(['box:read'])]
    private Collection $trips;

    /** @var Collection<int, TravelBox> */
    #[ORM\OneToMany(mappedBy: 'parentTravelBox', targetEntity: TravelBox::class)]
    private Collection $childTravelBoxes;

    /** @var Collection<int, BusinessBox> */
    #[ORM\OneToMany(mappedBy: 'travelBox', targetEntity: BusinessBox::class)]
    private Collection $businessBoxes;

    /** @var Collection<int, BlogBox> */
    #[ORM\OneToMany(mappedBy: 'travelBox', targetEntity: BlogBox::class)]
    private Collection $blogBoxes;

    public function __construct()
    {
        parent::__construct();
        $this->trips = new ArrayCollection();
        $this->childTravelBoxes = new ArrayCollection();
        $this->businessBoxes = new ArrayCollection();
        $this->blogBoxes = new ArrayCollection();
    }

    public function getType(): string { return self::TYPE_TRAVEL; }

    public function getParentTravelBox(): ?TravelBox { return $this->parentTravelBox; }
    public function setParentTravelBox(?TravelBox $parentTravelBox): self { $this->parentTravelBox = $parentTravelBox; return $this; }
    public function getBusinessBox(): ?BusinessBox { return $this->businessBox; }
    public function setBusinessBox(?BusinessBox $businessBox): self { $this->businessBox = $businessBox; return $this; }

    /** @return Collection<int, Trip> */
    public function getTrips(): Collection { return $this->trips; }
    /** @return Collection<int, TravelBox> */
    public function getChildTravelBoxes(): Collection { return $this->childTravelBoxes; }
    /** @return Collection<int, BusinessBox> */
    public function getBusinessBoxes(): Collection { return $this->businessBoxes; }
    /** @return Collection<int, BlogBox> */
    public function getBlogBoxes(): Collection { return $this->blogBoxes; }

    public function getParentBox(): ?Box { return $this->parentTravelBox ?? $this->businessBox; }

    #[Assert\Callback]
    public function validateSingleParent(ExecutionContextInterface $context): void
    {
        if ($this->parentTravelBox !== null && $this->businessBox !== null) {
            $context->buildViolation('Une TravelBox ne peut avoir qu\'un seul parent (Travel ou Business).')
                ->atPath('parentTravelBox')
                ->addViolation();
        }
    }

    public function addTrip(Trip $trip): self
    {
        if (!$this->trips->contains($trip)) {
            $this->trips->add($trip);
            $trip->setTravelBox($this);
        }
        return $this;
    }

    public function removeTrip(Trip $trip): self
    {
        if ($this->trips->removeElement($trip) && $trip->getTravelBox() === $this) {
            $trip->setTravelBox(null);
        }
        return $this;
    }
}
