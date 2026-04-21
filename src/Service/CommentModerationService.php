<?php

namespace App\Service;

use Symfony\Component\String\UnicodeString;

class CommentModerationService
{
    /**
     * @var array<string, list<string>>
     */
    private const BLOCKED_TERMS = [
        'racism' => ['nigger', 'nigga', 'chink', 'gook', 'kike', 'spic', 'wetback'],
        'profanity' => ['fuck', 'shit', 'bitch', 'asshole', 'motherfucker'],
        'offensive language' => ['retard', 'whore', 'slut'],
    ];

    public function moderate(string $content): CommentModerationResult
    {
        $normalized = $this->normalize($content);

        foreach (self::BLOCKED_TERMS as $reason => $terms) {
            foreach ($terms as $term) {
                if (preg_match($this->wordPattern($term), $normalized) === 1) {
                    return CommentModerationResult::blocked($reason);
                }
            }
        }

        return CommentModerationResult::approved();
    }

    private function normalize(string $content): string
    {
        $normalized = new UnicodeString($content);

        return $normalized
            ->ascii()
            ->lower()
            ->toString();
    }

    private function wordPattern(string $term): string
    {
        return '/(^|[^a-z0-9])' . preg_quote($term, '/') . '([^a-z0-9]|$)/';
    }
}
