<?php

namespace App\Services\BlogVk;

use App\Models\BlogPost;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlogVkMessageBuilder
{
    public function __construct(
        private readonly BlogVkSettings $settings,
        private readonly BlogVkAiMessageGenerator $aiGenerator,
    ) {
    }

    public function build(BlogPost $post, string $publicUrl): string
    {
        $custom = trim((string) ($post->vk_message ?? ''));
        if ($custom !== '') {
            return $this->truncate($custom);
        }

        if ($this->settings->aiEnabled()) {
            $generated = $this->aiGenerator->generate($post->loadMissing('category'));
            if ($generated !== null && $generated !== '') {
                $post->update(['vk_message' => $generated]);
                $post->vk_message = $generated;

                return $this->truncate($generated);
            }

            Log::channel('queue')->info('VK message: AI skipped, using template fallback', [
                'blog_post_id' => $post->id,
            ]);
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
