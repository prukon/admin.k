<?php

namespace App\Services\BlogVk;

use App\Models\Setting;

class BlogVkSettings
{
    public function globallyEnabled(): bool
    {
        return $this->disabledReason() === null;
    }

    public function disabledReason(): ?string
    {
        if (!config('services.vk.enabled')) {
            return 'VK отключён в .env (VK_ENABLED=false).';
        }

        $token = (string) config('services.vk.access_token', '');
        if ($token === '') {
            return 'Не задан VK_GROUP_TOKEN в .env.';
        }

        $groupId = (string) config('services.vk.group_id', '');
        if ($groupId === '' || (int) $groupId <= 0) {
            return 'Не задан или некорректен VK_GROUP_ID в .env.';
        }

        if (!$this->adminEnabled()) {
            return 'Публикация в VK выключена в настройках блога (/admin/blog/settings).';
        }

        return null;
    }

    public function adminEnabled(): bool
    {
        $value = $this->getText('blog.vk.enabled', '1');

        return $value === null || $value === '' || $value === '1';
    }

    public function messageTemplate(): string
    {
        return $this->getText(
            'blog.vk.message_template',
            "{title}\n\n{excerpt}\n\n{url}"
        ) ?: "{title}\n\n{excerpt}\n\n{url}";
    }

    public function groupId(): int
    {
        return (int) config('services.vk.group_id');
    }

    public function accessToken(): string
    {
        return (string) config('services.vk.access_token');
    }

    public function userAccessToken(): string
    {
        return (string) config('services.vk.user_access_token', '');
    }

    public function canUploadPhotos(): bool
    {
        return $this->userAccessToken() !== '';
    }

    public function apiVersion(): string
    {
        return (string) config('services.vk.api_version', '5.199');
    }

    /**
     * @return array{source: string, medium: string, campaign: string}
     */
    public function utmParams(): array
    {
        return [
            'source' => (string) config('services.vk.utm.source', 'vk'),
            'medium' => (string) config('services.vk.utm.medium', 'social'),
            'campaign' => (string) config('services.vk.utm.campaign', 'blog'),
        ];
    }

    private function getText(string $name, ?string $default = null): ?string
    {
        $row = Setting::query()
            ->where('name', $name)
            ->whereNull('partner_id')
            ->first(['text']);

        $text = $row?->text;

        return ($text === null || $text === '') ? $default : $text;
    }
}
