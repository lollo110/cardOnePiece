<?php

namespace App\Entity;

use App\Repository\CardPriceHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardPriceHistoryRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_card_price_history_day', columns: ['card_id', 'language_key', 'recorded_on'])]
#[ORM\Index(columns: ['language_key'], name: 'idx_card_price_history_language')]
#[ORM\Index(columns: ['recorded_on'], name: 'idx_card_price_history_recorded_on')]
class CardPriceHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Card::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Card $card = null;

    #[ORM\Column(length: 80)]
    private string $languageKey = '';

    #[ORM\Column(length: 120)]
    private string $languageLabel = '';

    #[ORM\Column]
    private int $averageNearMintPriceCents = 0;

    #[ORM\Column]
    private int $productCount = 0;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $recordedOn;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->recordedOn = new \DateTimeImmutable('today');
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCard(): ?Card { return $this->card; }
    public function setCard(?Card $card): self { $this->card = $card; return $this; }
    public function getLanguageKey(): string { return $this->languageKey; }
    public function setLanguageKey(string $languageKey): self { $this->languageKey = $languageKey; return $this; }
    public function getLanguageLabel(): string { return $this->languageLabel; }
    public function setLanguageLabel(string $languageLabel): self { $this->languageLabel = $languageLabel; return $this; }
    public function getAverageNearMintPriceCents(): int { return $this->averageNearMintPriceCents; }
    public function setAverageNearMintPriceCents(int $averageNearMintPriceCents): self { $this->averageNearMintPriceCents = $averageNearMintPriceCents; return $this; }
    public function getProductCount(): int { return $this->productCount; }
    public function setProductCount(int $productCount): self { $this->productCount = $productCount; return $this; }
    public function getRecordedOn(): \DateTimeImmutable { return $this->recordedOn; }
    public function setRecordedOn(\DateTimeImmutable $recordedOn): self { $this->recordedOn = $recordedOn; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
