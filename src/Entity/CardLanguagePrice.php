<?php

namespace App\Entity;

use App\Repository\CardLanguagePriceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardLanguagePriceRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_card_language_price', columns: ['card_id', 'language_key'])]
#[ORM\Index(columns: ['language_key'], name: 'idx_card_language_price_language')]
class CardLanguagePrice
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

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
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
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
