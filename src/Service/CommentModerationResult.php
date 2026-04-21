<?php

namespace App\Service;

class CommentModerationResult
{
    private function __construct(
        private readonly bool $approved,
        private readonly ?string $reason = null,
    ) {
    }

    public static function approved(): self
    {
        return new self(true);
    }

    public static function blocked(string $reason): self
    {
        return new self(false, $reason);
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
