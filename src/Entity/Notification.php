<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Index(columns: ['recipient_id', 'read_at'], name: 'idx_notification_recipient_read')]
class Notification
{
    public const TYPE_REPLY = 'reply';
    public const SOURCE_CARD = 'card';
    public const SOURCE_BLOG = 'blog';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $actorUser = null;

    #[ORM\Column(length: 120)]
    private string $actorName = '';

    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_REPLY;

    #[ORM\Column(length: 30)]
    private string $sourceType = self::SOURCE_CARD;

    #[ORM\Column(length: 180)]
    private string $targetTitle = '';

    #[ORM\Column(length: 500)]
    private string $targetUrl = '';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getRecipient(): User { return $this->recipient; }
    public function setRecipient(User $recipient): self { $this->recipient = $recipient; return $this; }
    public function getActorUser(): ?User { return $this->actorUser; }
    public function setActorUser(?User $actorUser): self { $this->actorUser = $actorUser; return $this; }
    public function getActorName(): string { return $this->actorName; }
    public function setActorName(string $actorName): self { $this->actorName = trim($actorName); return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getSourceType(): string { return $this->sourceType; }
    public function setSourceType(string $sourceType): self { $this->sourceType = $sourceType; return $this; }
    public function getTargetTitle(): string { return $this->targetTitle; }
    public function setTargetTitle(string $targetTitle): self { $this->targetTitle = trim($targetTitle); return $this; }
    public function getTargetUrl(): string { return $this->targetUrl; }
    public function setTargetUrl(string $targetUrl): self { $this->targetUrl = $targetUrl; return $this; }
    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function setReadAt(?\DateTimeImmutable $readAt): self { $this->readAt = $readAt; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function isRead(): bool { return $this->readAt !== null; }
}
