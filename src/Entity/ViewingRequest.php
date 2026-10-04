<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'viewing_requests')]
class ViewingRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'viewingRequests')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'viewingRequests')]
    #[ORM\JoinColumn(name: 'dormitory_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Dormitory $dormitory = null;

    #[ORM\Column(name: 'preferred_date', type: 'date_immutable')]
    private ?\DateTimeImmutable $preferredDate = null;

    #[ORM\Column(name: 'preferred_time', type: 'time_immutable')]
    private ?\DateTimeImmutable $preferredTime = null;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getDormitory(): ?Dormitory { return $this->dormitory; }
    public function setDormitory(?Dormitory $dormitory): static { $this->dormitory = $dormitory; return $this; }

    public function getPreferredDate(): ?\DateTimeImmutable { return $this->preferredDate; }
    public function setPreferredDate(\DateTimeImmutable $preferredDate): static { $this->preferredDate = $preferredDate; return $this; }

    public function getPreferredTime(): ?\DateTimeImmutable { return $this->preferredTime; }
    public function setPreferredTime(\DateTimeImmutable $preferredTime): static { $this->preferredTime = $preferredTime; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
