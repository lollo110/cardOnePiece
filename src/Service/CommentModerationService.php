<?php

namespace App\Service;

use Symfony\Component\String\UnicodeString;

class CommentModerationService
{
    /**
     * @var array<string, list<string>>
     */
    private const BLOCKED_TERMS = [
        'racism' => [
            'nigger',
            'nigga',
            'chink',
            'gook',
            'kike',
            'spic',
            'wetback',
            'coon',
            'bougnoule',
            'youpin',
            'chinetoque',
            'bicot',
        ],
        'profanity' => [
            'fuck',
            'fucking',
            'fucked',
            'shit',
            'bullshit',
            'bitch',
            'bastard',
            'asshole',
            'cunt',
            'dickhead',
            'motherfucker',
            'piss off',
            'merde',
            'putain',
            'pute',
            'fdp',
            'bordel',
            'connard',
            'connasse',
            'encule',
            'enculer',
            'enfoire',
            'enflure',
            'batard',
            'fils de pute',
            'sale pute',
            'va te faire foutre',
            'nique ta mere',
            'ntm',
        ],
        'offensive language' => [
            'retard',
            'whore',
            'slut',
            'con',
            'conne',
            'salope',
            'ta gueule',
            'ferme ta gueule',
            'tg',
            'ftg',
            'debile',
            'cretin',
            'abruti',
            'trou du cul',
        ],
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
            ->trim()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->toString();
    }

    private function wordPattern(string $term): string
    {
        $termPattern = preg_quote($term, '/');
        $termPattern = str_replace('\ ', '\s+', $termPattern);

        return '/(^|[^a-z0-9])' . $termPattern . '([^a-z0-9]|$)/';
    }
}
