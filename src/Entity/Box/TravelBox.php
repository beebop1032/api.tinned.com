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
    /** @var Collection<int, Trip> */
    #[ORM\OneToMany(mappedBy: 'travelBox', targetEntity: Trip::class)]
    #[Groups(['box:read'])]
    private Collection $trips;

    public function __construct()
    {
        parent::__construct();
        $this->trips = new ArrayCollection();
    }

    public function getType(): string { return self::TYPE_TRAVEL; }

    /** @return Collection<int, Trip> */
    public function getTrips(): Collection { return $this->trips; }

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
