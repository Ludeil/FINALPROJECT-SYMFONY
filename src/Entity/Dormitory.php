<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dormitories')]
class Dormitory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 150)]
    private ?string $location = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'dormitory', targetEntity: Room::class)]
    private Collection $rooms;

    #[ORM\OneToMany(mappedBy: 'dormitory', targetEntity: ViewingRequest::class)]
    private Collection $viewingRequests;

    public function __construct()
    {
        $this->rooms = new ArrayCollection();
        $this->viewingRequests = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(string $location): static { $this->location = $location; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    /** @return Collection<int, Room> */
    public function getRooms(): Collection { return $this->rooms; }

    public function addRoom(Room $room): static
    {
        if (!$this->rooms->contains($room)) {
            $this->rooms->add($room);
            $room->setDormitory($this);
        }
        return $this;
    }

    public function removeRoom(Room $room): static
    {
        $this->rooms->removeElement($room);
        return $this;
    }

    /** @return Collection<int, ViewingRequest> */
    public function getViewingRequests(): Collection { return $this->viewingRequests; }

    public function addViewingRequest(ViewingRequest $viewingRequest): static
    {
        if (!$this->viewingRequests->contains($viewingRequest)) {
            $this->viewingRequests->add($viewingRequest);
            $viewingRequest->setDormitory($this);
        }
        return $this;
    }

    public function removeViewingRequest(ViewingRequest $viewingRequest): static
    {
        $this->viewingRequests->removeElement($viewingRequest);
        return $this;
    }
}
