<?php

declare(strict_types=1);

namespace App\Services\Chat;

final class ChatEmoji
{
    /**
     * Короткий набор для кнопки справа в поле ввода (~80).
     *
     * @var list<string>
     */
    public const COMPOSER = [
        '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇',
        '🙂', '😉', '😍', '🥰', '😘', '😋', '😜', '🤪', '😎', '🤩',
        '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '😣', '😢',
        '😭', '😤', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '🤗',
        '🤔', '🤭', '😴', '🤮', '🤧', '🤠', '💩', '👻', '💀', '🤖',
        '👍', '👎', '👏', '🙏', '👌', '✌️', '🤞', '👋', '🤝', '💪',
        '🫶', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '💔', '💕',
        '🔥', '⭐', '✨', '🎉', '💯', '⚡', '🌸', '⚽', '🏆', '🎁',
    ];

    /**
     * Реакции на сообщение (как в Telegram: несколько чипов, у человека один смайл).
     *
     * @var list<string>
     */
    public const REACTIONS = [
        '👍', '❤️', '🔥', '🥰', '👏', '😁', '🎉', '😢', '😍', '🤣',
        '🙏', '👎', '💯', '⚡', '😎', '🤯', '🤝', '😭', '🤩', '💩',
        '👌', '🤔', '🤗', '🙈',
    ];

    /**
     * @return list<string>
     */
    public static function composer(): array
    {
        return self::COMPOSER;
    }

    /**
     * @return list<string>
     */
    public static function reactions(): array
    {
        return self::REACTIONS;
    }

    public static function isAllowedReaction(string $emoji): bool
    {
        return in_array($emoji, self::REACTIONS, true);
    }

    /**
     * 1–3 смайла без другого текста — крупный пузырь как в Telegram.
     */
    public static function isBigEmojiBody(string $body): bool
    {
        $count = self::emojiOnlyCount($body);

        return $count >= 1 && $count <= 3;
    }

    public static function emojiOnlyCount(string $body): int
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return 0;
        }

        $compact = preg_replace('/\s+/u', '', $trimmed) ?? '';
        if ($compact === '') {
            return 0;
        }

        $matches = [];
        $found = preg_match_all(self::sequencePattern(), $compact, $matches);
        if (! is_int($found) || $found < 1) {
            return 0;
        }

        $joined = implode('', $matches[0]);
        if ($joined !== $compact) {
            return 0;
        }

        return $found;
    }

    private static function sequencePattern(): string
    {
        return '/(?:[\x{1F1E6}-\x{1F1FF}]{2}|\p{Extended_Pictographic}(?:\x{FE0F}|\x{FE0E})?(?:\x{200D}\p{Extended_Pictographic}(?:\x{FE0F}|\x{FE0E})?)*|[0-9#*]\x{FE0F}?\x{20E3})/u';
    }
}
