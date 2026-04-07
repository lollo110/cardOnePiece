<?php

namespace App\Entity;

use App\Repository\CardArtistRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardArtistRepository::class)]
class CardArtist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true, nullable: true)]
    private ?int $apiId = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    public function getId(): ?int { return $this->id; }
    public function getApiId(): ?int { return $this->apiId; }
    public function setApiId(?int $apiId): self { $this->apiId = $apiId; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self { $this->slug = $slug; return $this; }
}
