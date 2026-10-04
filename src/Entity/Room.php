<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rooms')]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rooms')]
    #[ORM\JoinColumn(name: 'dormitory_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Dormitory $dormitory = null;

    #[ORM\Column(name: 'room_number', length: 30)]
    private ?string $roomNumber = null;

    #[ORM\Column(name: 'room_type', length: 30)]
    private string $roomType = '4_person';

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private int $capacity = 4;

    #[ORM\Column(name: 'monthly_rate', type: 'decimal', precision: 10, scale: 2)]
    private string $monthlyRate = '0.00';

    #[ORM\Column(name: 'available_slots', type: 'smallint', options: ['unsigned' => true])]
    private int $availableSlots = 0;

    #[ORM\Column(length: 20)]
    private string $status = 'available';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'room', targetEntity: RoomApplication::class)]
    private Collection $roomApplications;

    public function __construct()
    {
        $this->roomApplications = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getDormitory(): ?Dormitory { return $this->dormitory; }
    public function setDormitory(?Dormitory $dormitory): static { $this->dormitory = $dormitory; return $this; }

    public function getRoomNumber(): ?string { return $this->roomNumber; }
    public function setRoomNumber(string $roomNumber): static { $this->roomNumber = $roomNumber; return $this; }

    public function getRoomType(): string { return $this->roomType; }
    public function setRoomType(string $roomType): static { $this->roomType = $roomType; return $this; }

    public function getCapacity(): int { return $this->capacity; }
    public function setCapacity(int $capacity): static { $this->capacity = $capacity; return $this; }

    public function getMonthlyRate(): string { return $this->monthlyRate; }
    public function setMonthlyRate(string $monthlyRate): static { $this->monthlyRate = $monthlyRate; return $this; }

    public function getAvailableSlots(): int { return $this->availableSlots; }
    public function setAvailableSlots(int $availableSlots): static { $this->availableSlots = $availableSlots; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    /** @return Collection<int, RoomApplication> */
    public function getRoomApplications(): Collection { return $this->roomApplications; }

    public function addRoomApplication(RoomApplication $roomApplication): static
    {
        if (!$this->roomApplications->contains($roomApplication)) {
            $this->roomApplications->add($roomApplication);
            $roomApplication->setRoom($this);
        }
        return $this;
    }

    public function removeRoomApplication(RoomApplication $roomApplication): static
    {
        $this->roomApplications->removeElement($roomApplication);
        return $this;
    }
}
