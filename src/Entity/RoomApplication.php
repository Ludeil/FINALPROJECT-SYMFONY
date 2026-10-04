<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'room_applications')]
class RoomApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'roomApplications')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'roomApplications')]
    #[ORM\JoinColumn(name: 'room_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Room $room = null;

    #[ORM\Column(name: 'move_in_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $moveInDate = null;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(name: 'move_out_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $moveOutDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getRoom(): ?Room { return $this->room; }
    public function setRoom(?Room $room): static { $this->room = $room; return $this; }

    public function getMoveInDate(): ?\DateTimeImmutable { return $this->moveInDate; }
    public function setMoveInDate(?\DateTimeImmutable $moveInDate): static { $this->moveInDate = $moveInDate; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getMoveOutDate(): ?\DateTimeImmutable { return $this->moveOutDate; }
    public function setMoveOutDate(?\DateTimeImmutable $moveOutDate): static { $this->moveOutDate = $moveOutDate; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
