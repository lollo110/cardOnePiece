<?php

namespace App\Entity;

use App\Repository\CardPriceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardPriceRepository::class)]
class CardPrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Card $card;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(nullable: true)]
    private ?float $lowestNearMint = null;

    #[ORM\Column(nullable: true)]
    private ?float $lowestNearMintEuOnly = null;

    #[ORM\Column(nullable: true)]
    private ?float $lowestNearMintFr = null;

    #[ORM\Column(nullable: true)]
    private ?float $lowestNearMintFrEuOnly = null;

    #[ORM\Column(nullable: true)]
    private ?float $average7d = null;

    #[ORM\Column(nullable: true)]
    private ?float $average30d = null;

    #[ORM\Column(nullable: true)]
    private ?float $tcgplayerMarketPrice = null;

    #[ORM\Column(nullable: true)]
    private ?array $rawData = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCard(): Card { return $this->card; }
    public function setCard(Card $card): self { $this->card = $card; return $this; }
    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $currency): self { $this->currency = $currency; return $this; }
    public function getLowestNearMint(): ?float { return $this->lowestNearMint; }
    public function setLowestNearMint(?float $lowestNearMint): self { $this->lowestNearMint = $lowestNearMint; return $this; }
    public function getLowestNearMintEuOnly(): ?float { return $this->lowestNearMintEuOnly; }
    public function setLowestNearMintEuOnly(?float $lowestNearMintEuOnly): self { $this->lowestNearMintEuOnly = $lowestNearMintEuOnly; return $this; }
    public function getLowestNearMintFr(): ?float { return $this->lowestNearMintFr; }
    public function setLowestNearMintFr(?float $lowestNearMintFr): self { $this->lowestNearMintFr = $lowestNearMintFr; return $this; }
    public function getLowestNearMintFrEuOnly(): ?float { return $this->lowestNearMintFrEuOnly; }
    public function setLowestNearMintFrEuOnly(?float $lowestNearMintFrEuOnly): self { $this->lowestNearMintFrEuOnly = $lowestNearMintFrEuOnly; return $this; }
    public function getAverage7d(): ?float { return $this->average7d; }
    public function setAverage7d(?float $average7d): self { $this->average7d = $average7d; return $this; }
    public function getAverage30d(): ?float { return $this->average30d; }
    public function setAverage30d(?float $average30d): self { $this->average30d = $average30d; return $this; }
    public function getTcgplayerMarketPrice(): ?float { return $this->tcgplayerMarketPrice; }
    public function setTcgplayerMarketPrice(?float $tcgplayerMarketPrice): self { $this->tcgplayerMarketPrice = $tcgplayerMarketPrice; return $this; }
    public function getRawData(): ?array { return $this->rawData; }
    public function setRawData(?array $rawData): self { $this->rawData = $rawData; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
