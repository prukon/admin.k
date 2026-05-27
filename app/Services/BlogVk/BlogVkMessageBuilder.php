<?php

namespace App\Services\BlogVk;

use App\Models\BlogPost;
use Illuminate\Support\Str;

class BlogVkMessageBuilder
{
    public function __construct(
        private readonly BlogVkSettings $settings,
    ) {
    }

    public function build(BlogPost $post, string $publicUrl): string
    {
        $custom = trim((string) ($post->vk_message ?? ''));
        if ($custom !== '') {
            return $this->truncate($custom);
        }

        $excerpt = trim(strip_tags((string) ($post->excerpt ?? '')));
        if ($excerpt === '') {
            $plain = trim(strip_tags((string) $post->content));
            $excerpt = $plain !== '' ? Str::limit($plain, 200, '…') : '';
        }

        $template = $this->settings->messageTemplate();
        $message = str_replace(
            ['{title}', '{excerpt}', '{url}', '{category}'],
            [
                $post->title,
                $excerpt,
                $publicUrl,
                $post->category?->name ?? '',
            ],
            $template
        );

        return $this->truncate(trim($message));
    }

    private function truncate(string $message): string
    {
        return mb_substr($message, 0, 500, 'UTF-8');
    }
}
