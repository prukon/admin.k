<?php

namespace App\Services\BlogAi;

class BlogAiImagePromptBuilder
{
    public function __construct(
        private readonly BlogAiImageSettings $settings,
    ) {
    }

    public function build(string $topic, string $purpose, ?string $extra = null): string
    {
        $parts = [];

        $parts[] = 'Сгенерируй современную плоскую иллюстрацию для блога kidscrm.online.';
        $parts[] = 'Стиль: ' . trim($this->settings->style());
        $parts[] = 'Палитра (HEX): ' . trim($this->settings->palette());
        $parts[] = 'Правила: ' . trim($this->settings->rules());

        // Hard rules per requirements
        $parts[] = 'Строго: без любого текста, букв, цифр, логотипов, водяных знаков, интерфейсных надписей, вывесок с текстом.';
        $parts[] = 'Можно: персонажи/люди в виде иллюстраций.';

        $parts[] = 'Тема: ' . trim($topic);
        $parts[] = 'Назначение: ' . trim($purpose);

        if ($extra !== null && trim($extra) !== '') {
            $parts[] = 'Дополнительно: ' . trim($extra);
        }

        $parts[] = 'Композиция: чистая, без мелких деталей, хорошо читается на мобильном.';
        $parts[] = 'Фон: простой, без шумных паттернов.';

        return implode("\n", array_filter($parts));
    }
}

