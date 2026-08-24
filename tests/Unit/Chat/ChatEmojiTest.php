<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use App\Services\Chat\ChatEmoji;
use PHPUnit\Framework\TestCase;

final class ChatEmojiTest extends TestCase
{
    public function test_composer_set_is_short_and_contains_common_smileys(): void
    {
        $list = ChatEmoji::composer();
        $this->assertCount(80, $list);
        $this->assertContains('😀', $list);
        $this->assertContains('👍', $list);
        $this->assertContains('❤️', $list);
    }

    public function test_reaction_allowlist_is_fixed(): void
    {
        $this->assertTrue(ChatEmoji::isAllowedReaction('👍'));
        $this->assertTrue(ChatEmoji::isAllowedReaction('❤️'));
        $this->assertFalse(ChatEmoji::isAllowedReaction('😀'));
        $this->assertFalse(ChatEmoji::isAllowedReaction('abc'));
        $this->assertFalse(ChatEmoji::isAllowedReaction(''));
    }

    public function test_big_emoji_body_is_one_to_three_smileys_only(): void
    {
        $this->assertTrue(ChatEmoji::isBigEmojiBody('👍'));
        $this->assertTrue(ChatEmoji::isBigEmojiBody(' ❤️ '));
        $this->assertTrue(ChatEmoji::isBigEmojiBody('👍❤️'));
        $this->assertTrue(ChatEmoji::isBigEmojiBody('👍 ❤️ 🔥'));
        $this->assertSame(3, ChatEmoji::emojiOnlyCount('👍❤️🔥'));
        $this->assertFalse(ChatEmoji::isBigEmojiBody('👍❤️🔥🎉'));
        $this->assertFalse(ChatEmoji::isBigEmojiBody('Привет'));
        $this->assertFalse(ChatEmoji::isBigEmojiBody('👍 привет'));
        $this->assertFalse(ChatEmoji::isBigEmojiBody(''));
        $this->assertFalse(ChatEmoji::isBigEmojiBody('   '));
    }
}
