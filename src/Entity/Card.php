<?php

namespace App\Entity;

use App\Repository\CardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardRepository::class)]
#[ORM\Index(columns: ['name'], name: 'idx_card_name')]
#[ORM\Index(columns: ['slug'], name: 'idx_card_slug')]
class Card
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private int $apiId;

    #[ORM\ManyToOne]
    private ?CardEpisode $episode = null;

    #[ORM\ManyToOne]
    private ?CardArtist $artist = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameNumbered = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cardNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $hp = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $rarity = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $supertype = null;

    #[ORM\Column(nullable: true)]
    private ?int $tcgid = null;

    #[ORM\Column(nullable: true)]
    private ?int $cardmarketId = null;

    #[ORM\Column(nullable: true)]
    private ?int $tcgplayerId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $tcggoUrl = null;

    #[ORM\Column(nullable: true)]
    private ?array $links = null;

    #[ORM\Column(nullable: true)]
    private ?array $rawData = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getApiId(): int { return $this->apiId; }
    public function setApiId(int $apiId): self { $this->apiId = $apiId; return $this; }
    public function getEpisode(): ?CardEpisode { return $this->episode; }
    public function setEpisode(?CardEpisode $episode): self { $this->episode = $episode; return $this; }
    public function getArtist(): ?CardArtist { return $this->artist; }
    public function setArtist(?CardArtist $artist): self { $this->artist = $artist; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getNameNumbered(): ?string { return $this->nameNumbered; }
    public function setNameNumbered(?string $nameNumbered): self { $this->nameNumbered = $nameNumbered; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self { $this->slug = $slug; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): self { $this->type = $type; return $this; }
    public function getCardNumber(): ?string { return $this->cardNumber; }
    public function setCardNumber(?string $cardNumber): self { $this->cardNumber = $cardNumber; return $this; }
    public function getHp(): ?string { return $this->hp; }
    public function setHp(?string $hp): self { $this->hp = $hp; return $this; }
    public function getRarity(): ?string { return $this->rarity; }
    public function setRarity(?string $rarity): self { $this->rarity = $rarity; return $this; }
    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): self { $this->color = $color; return $this; }
    public function getVersion(): ?string { return $this->version; }
    public function setVersion(?string $version): self { $this->version = $version; return $this; }
    public function getSupertype(): ?string { return $this->supertype; }
    public function setSupertype(?string $supertype): self { $this->supertype = $supertype; return $this; }
    public function getTcgid(): ?int { return $this->tcgid; }
    public function setTcgid(?int $tcgid): self { $this->tcgid = $tcgid; return $this; }
    public function getCardmarketId(): ?int { return $this->cardmarketId; }
    public function setCardmarketId(?int $cardmarketId): self { $this->cardmarketId = $cardmarketId; return $this; }
    public function getTcgplayerId(): ?int { return $this->tcgplayerId; }
    public function setTcgplayerId(?int $tcgplayerId): self { $this->tcgplayerId = $tcgplayerId; return $this; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): self { $this->image = $image; return $this; }
    public function getTcggoUrl(): ?string { return $this->tcggoUrl; }
    public function setTcggoUrl(?string $tcggoUrl): self { $this->tcggoUrl = $tcggoUrl; return $this; }
    public function getLinks(): ?array { return $this->links; }
    public function setLinks(?array $links): self { $this->links = $links; return $this; }
    public function getRawData(): ?array { return $this->rawData; }
    public function setRawData(?array $rawData): self { $this->rawData = $rawData; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
