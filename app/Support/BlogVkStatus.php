<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\BlogPostSocialPublication;

final class BlogVkStatus
{
    /**
     * @return array{label: string, badge: string, can_retry: bool}
     */
    public static function forPost(BlogPost $post, bool $globalEnabled): array
    {
        if (!$globalEnabled) {
            return ['label' => '—', 'badge' => 'bg-secondary', 'can_retry' => false];
        }

        if (!$post->publish_to_vk) {
            return ['label' => 'Выкл', 'badge' => 'bg-secondary', 'can_retry' => false];
        }

        $publication = $post->socialPublications
            ->firstWhere('platform', BlogPostSocialPublication::PLATFORM_VK);

        if (!$publication) {
            if ($post->isScheduledForFuture()) {
                return ['label' => 'Запланировано', 'badge' => 'bg-info', 'can_retry' => false];
            }
            if (!$post->isPubliclyVisible()) {
                return ['label' => '—', 'badge' => 'bg-secondary', 'can_retry' => false];
            }
            if (empty($post->cover_image_path)) {
                return ['label' => 'Ждёт обложку', 'badge' => 'bg-warning text-dark', 'can_retry' => false];
            }

            return ['label' => 'В очереди', 'badge' => 'bg-info', 'can_retry' => false];
        }

        return match ($publication->status) {
            BlogPostSocialPublication::STATUS_PUBLISHED => [
                'label' => 'Опубликовано',
                'badge' => 'bg-success',
                'can_retry' => false,
            ],
            BlogPostSocialPublication::STATUS_PENDING_COVER => [
                'label' => 'Ждёт обложку',
                'badge' => 'bg-warning text-dark',
                'can_retry' => false,
            ],
            BlogPostSocialPublication::STATUS_PENDING_SCHEDULE => [
                'label' => 'Запланировано',
                'badge' => 'bg-info',
                'can_retry' => false,
            ],
            BlogPostSocialPublication::STATUS_PENDING => [
                'label' => 'В очереди',
                'badge' => 'bg-info',
                'can_retry' => false,
            ],
            BlogPostSocialPublication::STATUS_PUBLISHING => [
                'label' => 'Публикуется…',
                'badge' => 'bg-primary',
                'can_retry' => false,
            ],
            BlogPostSocialPublication::STATUS_FAILED => [
                'label' => 'Ошибка',
                'badge' => 'bg-danger',
                'can_retry' => true,
            ],
            BlogPostSocialPublication::STATUS_SKIPPED => [
                'label' => 'Пропущено',
                'badge' => 'bg-secondary',
                'can_retry' => false,
            ],
            default => [
                'label' => '—',
                'badge' => 'bg-secondary',
                'can_retry' => false,
            ],
        };
    }
}
