<?php

namespace App\Services\BlogVk;

use App\Models\Setting;

class BlogVkSettings
{
    public function globallyEnabled(): bool
    {
        if (!config('services.vk.enabled')) {
            return false;
        }

        $token = (string) config('services.vk.access_token', '');
        $groupId = (string) config('services.vk.group_id', '');

        if ($token === '' || $groupId === '') {
            return false;
        }

        return $this->adminEnabled();
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
