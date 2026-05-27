<?php

namespace App\Services\BlogVk;

use App\Jobs\BlogVk\PublishBlogPostToVkJob;
use App\Models\BlogPost;
use App\Models\BlogPostSocialPublication;
class BlogVkPublicationCoordinator
{
    public function __construct(
        private readonly BlogVkSettings $settings,
    ) {
    }

    public function syncForPost(BlogPost $post): void
    {
        if (!$this->settings->globallyEnabled()) {
            return;
        }

        $publication = $this->getOrCreatePublication($post);

        if ($publication->status === BlogPostSocialPublication::STATUS_PUBLISHED) {
            return;
        }

        if (!$post->publish_to_vk) {
            if (!$publication->isTerminal()) {
                $publication->update([
                    'status' => BlogPostSocialPublication::STATUS_SKIPPED,
                    'error_message' => null,
                ]);
            }

            return;
        }

        if ($post->isScheduledForFuture()) {
            $publication->update([
                'status' => BlogPostSocialPublication::STATUS_PENDING_SCHEDULE,
                'error_message' => null,
            ]);

            return;
        }

        if (!$post->isPubliclyVisible()) {
            if (!$publication->isTerminal()) {
                $publication->update([
                    'status' => BlogPostSocialPublication::STATUS_SKIPPED,
                    'error_message' => null,
                ]);
            }

            return;
        }

        if (empty($post->cover_image_path)) {
            $publication->update([
                'status' => BlogPostSocialPublication::STATUS_PENDING_COVER,
                'error_message' => null,
            ]);

            return;
        }

        if ($publication->status === BlogPostSocialPublication::STATUS_PUBLISHING) {
            return;
        }

        $publication->update([
            'status' => BlogPostSocialPublication::STATUS_PENDING,
            'error_message' => null,
        ]);

        dispatch(new PublishBlogPostToVkJob($post->id));
    }

    public function retry(BlogPost $post): void
    {
        if (!$this->settings->globallyEnabled()) {
            return;
        }

        $publication = $this->getOrCreatePublication($post);

        if ($publication->status === BlogPostSocialPublication::STATUS_PUBLISHED) {
            return;
        }

        if (!$post->publish_to_vk || !$post->isPubliclyVisible() || empty($post->cover_image_path)) {
            $this->syncForPost($post->fresh(['category', 'socialPublications']));

            return;
        }

        $publication->update([
            'status' => BlogPostSocialPublication::STATUS_PENDING,
            'error_message' => null,
        ]);

        dispatch(new PublishBlogPostToVkJob($post->id));
    }

    /**
     * Обработка отложенных и ожидающих обложку (cron).
     */
    public function processDuePublications(): int
    {
        if (!$this->settings->globallyEnabled()) {
            return 0;
        }

        $count = 0;

        $postIds = BlogPostSocialPublication::query()
            ->where('platform', BlogPostSocialPublication::PLATFORM_VK)
            ->whereIn('status', [
                BlogPostSocialPublication::STATUS_PENDING_COVER,
                BlogPostSocialPublication::STATUS_PENDING_SCHEDULE,
                BlogPostSocialPublication::STATUS_PENDING,
                BlogPostSocialPublication::STATUS_FAILED,
            ])
            ->pluck('blog_post_id');

        $missingPublicationIds = BlogPost::query()
            ->where('publish_to_vk', true)
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->whereDoesntHave('socialPublications', function ($q) {
                $q->where('platform', BlogPostSocialPublication::PLATFORM_VK);
            })
            ->pluck('id');

        $ids = $postIds->merge($missingPublicationIds)->unique()->values();

        BlogPost::query()
            ->with(['category', 'socialPublications'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->each(function (BlogPost $post) use (&$count) {
                $this->syncForPost($post);
                $count++;
            });

        return $count;
    }

    private function getOrCreatePublication(BlogPost $post): BlogPostSocialPublication
    {
        $existing = BlogPostSocialPublication::query()
            ->where('blog_post_id', $post->id)
            ->where('platform', BlogPostSocialPublication::PLATFORM_VK)
            ->first();

        if ($existing) {
            return $existing;
        }

        return BlogPostSocialPublication::query()->create([
            'blog_post_id' => $post->id,
            'platform' => BlogPostSocialPublication::PLATFORM_VK,
            'status' => BlogPostSocialPublication::STATUS_PENDING_COVER,
        ]);
    }
}
