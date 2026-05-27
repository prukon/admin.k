<?php

namespace Tests\Feature\Crm\Blog\Concerns;

trait ProvidesBlogSettingsPayload
{
    /**
     * Минимально валидный payload для admin.blog.settings.update.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function validBlogSettingsPayload(array $overrides = []): array
    {
        return array_merge([
            'vk_enabled' => '1',
            'vk_ai_enabled' => '1',
            'vk_message_template' => "{title}\n\n{excerpt}\n\n{url}",
            'vk_ai_prompt_template' => str_repeat('Промпт для VK-анонса. Friendly tone, уникальный текст. ', 3),
            'ai_prompt_template' => str_repeat('A', 60),
        ], $overrides);
    }
}
