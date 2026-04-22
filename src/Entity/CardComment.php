<?php

namespace App\Entity;

use App\Repository\CardCommentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CardCommentRepository::class)]
class CardComment
{
    public const DISCUSSION_TRADING = 'trading';
    public const DISCUSSION_GAME = 'game';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_BLOCKED = 'blocked';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Card $card;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $authorUser = null;

    #[ORM\Column(length: 120)]
    #[Assert\Length(max: 120)]
    private string $authorName = 'Anonymous';

    #[ORM\Column(length: 20)]
    private string $discussionType = self::DISCUSSION_TRADING;

    #[ORM\Column(length: 40)]
    private string $language = 'english';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'validators.comment_required')]
    #[Assert\Length(min: 3, max: 2000)]
    private string $content = '';

    #[ORM\Column(length: 20)]
    private string $moderationStatus = self::STATUS_PUBLISHED;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $moderationReason = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCard(): Card { return $this->card; }
    public function setCard(Card $card): self { $this->card = $card; return $this; }
    public function getAuthorUser(): ?User { return $this->authorUser; }
    public function setAuthorUser(?User $authorUser): self { $this->authorUser = $authorUser; return $this; }
    public function getAuthorName(): string { return $this->authorName; }
    public function setAuthorName(string $authorName): self { $this->authorName = $authorName; return $this; }
    public function getDiscussionType(): string { return $this->discussionType; }
    public function setDiscussionType(string $discussionType): self { $this->discussionType = $discussionType; return $this; }
    public function getLanguage(): string { return $this->language; }
    public function setLanguage(string $language): self { $this->language = $language; return $this; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }
    public function getModerationStatus(): string { return $this->moderationStatus; }
    public function setModerationStatus(string $moderationStatus): self { $this->moderationStatus = $moderationStatus; return $this; }
    public function getModerationReason(): ?string { return $this->moderationReason; }
    public function setModerationReason(?string $moderationReason): self { $this->moderationReason = $moderationReason; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
