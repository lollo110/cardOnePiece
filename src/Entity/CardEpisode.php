<?php

namespace App\Entity;

use App\Repository\CardEpisodeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardEpisodeRepository::class)]
class CardEpisode
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

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $releasedAt = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(nullable: true)]
    private ?array $rawData = null;

    public function getId(): ?int { return $this->id; }
    public function getApiId(): ?int { return $this->apiId; }
    public function setApiId(?int $apiId): self { $this->apiId = $apiId; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self { $this->slug = $slug; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): self { $this->code = $code; return $this; }
    public function getReleasedAt(): ?\DateTimeImmutable { return $this->releasedAt; }
    public function setReleasedAt(?\DateTimeImmutable $releasedAt): self { $this->releasedAt = $releasedAt; return $this; }
    public function getLogo(): ?string { return $this->logo; }
    public function setLogo(?string $logo): self { $this->logo = $logo; return $this; }
    public function getRawData(): ?array { return $this->rawData; }
    public function setRawData(?array $rawData): self { $this->rawData = $rawData; return $this; }
}
