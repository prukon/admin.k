<?php

namespace App\Services\BlogVk;

use App\Jobs\BlogVk\PublishBlogPostToVkJob;
use App\Models\BlogPost;
use App\Models\BlogPostSocialPublication;
use Illuminate\Support\Facades\DB;

class BlogVkPublicationCoordinator
{
    /** Минут в статусе publishing без прогресса — считаем зависшим. */
    private const STALE_PUBLISHING_MINUTES = 10;

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
            if (!$this->isStalePublishing($publication)) {
                return;
            }

            $publication->update([
                'status' => BlogPostSocialPublication::STATUS_PENDING,
                'error_message' => 'Публикация прервалась (таймаут). Повторная постановка в очередь.',
            ]);
            $publication->refresh();
        }

        if ($publication->status === BlogPostSocialPublication::STATUS_FAILED) {
            // Ошибки не сбрасываем автоматически — только кнопка «Повторить» в админке.
            return;
        }

        if ($publication->status !== BlogPostSocialPublication::STATUS_PENDING) {
            $publication->update([
                'status' => BlogPostSocialPublication::STATUS_PENDING,
                'error_message' => null,
            ]);
            $publication->refresh();
        }

        if ($this->hasActiveJobInQueue($post->id)) {
            return;
        }

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
                BlogPostSocialPublication::STATUS_PUBLISHING,
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

    private function isStalePublishing(BlogPostSocialPublication $publication): bool
    {
        if ($publication->status !== BlogPostSocialPublication::STATUS_PUBLISHING) {
            return false;
        }

        $updatedAt = $publication->updated_at;
        if ($updatedAt === null) {
            return true;
        }

        return $updatedAt->lte(now()->subMinutes(self::STALE_PUBLISHING_MINUTES));
    }

    /**
     * Есть ли уже задача в таблице jobs (ожидает или отложена).
     */
    private function hasActiveJobInQueue(int $blogPostId): bool
    {
        if (!DB::getSchemaBuilder()->hasTable('jobs')) {
            return false;
        }

        $payloads = DB::table('jobs')
            ->where('payload', 'like', '%PublishBlogPostToVkJob%')
            ->pluck('payload');

        foreach ($payloads as $payload) {
            if ($this->payloadBelongsToPost((string) $payload, $blogPostId)) {
                return true;
            }
        }

        return false;
    }

    private function payloadBelongsToPost(string $payload, int $blogPostId): bool
    {
        $id = (string) $blogPostId;

        if (str_contains($payload, '"blogPostId":' . $id)) {
            return true;
        }

        // JSON payload: s:10:\"blogPostId\";i:16;
        if (str_contains($payload, 'blogPostId\";i:' . $id . ';')) {
            return true;
        }

        // PHP serialize (если payload без JSON-экранирования)
        if (str_contains($payload, 'blogPostId";i:' . $id . ';')) {
            return true;
        }

        return false;
    }
}
