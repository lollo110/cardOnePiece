<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CardSyncState
{
    #[ORM\Id]
    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastRunAt = null;

    public function __construct(string $name = 'daily_cards')
    {
        $this->name = $name;
    }

    public function getName(): string { return $this->name; }
    public function getLastRunAt(): ?\DateTimeImmutable { return $this->lastRunAt; }
    public function setLastRunAt(?\DateTimeImmutable $lastRunAt): self { $this->lastRunAt = $lastRunAt; return $this; }
}
