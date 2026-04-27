<?php

namespace App\Entity;

use App\Repository\DeckCardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeckCardRepository::class)]
#[ORM\Index(columns: ['section'], name: 'idx_deck_card_section')]
class DeckCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Deck::class, inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Deck $deck = null;

    #[ORM\ManyToOne(targetEntity: Card::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Card $card = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(length: 40)]
    private string $section = 'main';

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }
    public function getDeck(): ?Deck { return $this->deck; }
    public function setDeck(?Deck $deck): self { $this->deck = $deck; return $this; }
    public function getCard(): ?Card { return $this->card; }
    public function setCard(?Card $card): self { $this->card = $card; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = max(1, min(99, $quantity)); return $this; }
    public function getSection(): string { return $this->section; }
    public function setSection(string $section): self { $this->section = trim($section) !== '' ? mb_substr(trim($section), 0, 40) : 'main'; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = max(0, $position); return $this; }
}
