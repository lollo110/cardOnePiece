<?php

namespace App\Tests\Service;

use App\Service\CommentModerationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CommentModerationServiceTest extends TestCase
{
    #[DataProvider('blockedCommentProvider')]
    public function testBlocksFrenchAndEnglishInsults(string $content): void
    {
        $result = (new CommentModerationService())->moderate($content);

        self::assertFalse($result->isApproved());
    }

    public function testApprovesCleanComments(): void
    {
        $result = (new CommentModerationService())->moderate('I like this card and the deck list.');

        self::assertTrue($result->isApproved());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockedCommentProvider(): iterable
    {
        yield 'english profanity' => ['This is fucking bad.'];
        yield 'french profanity' => ['Tu es une pute.'];
        yield 'french accented profanity' => ['Espece de batard.'];
        yield 'french hyphen phrase' => ['Fils-de-pute.'];
        yield 'french acronym' => ['ntm'];
    }
}
