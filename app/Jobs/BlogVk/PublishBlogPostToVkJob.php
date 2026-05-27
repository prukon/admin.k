<?php

namespace App\Jobs\BlogVk;

use App\Models\BlogPost;
use App\Models\BlogPostSocialPublication;
use App\Services\BlogVk\BlogVkMessageBuilder;
use App\Services\BlogVk\BlogVkPublicationCoordinator;
use App\Services\BlogVk\BlogVkSettings;
use App\Services\BlogVk\BlogVkUrlBuilder;
use App\Services\BlogVk\Exceptions\VkApiException;
use App\Services\BlogVk\VkWallClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishBlogPostToVkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public readonly int $blogPostId,
    ) {
    }

    public function handle(
        BlogVkSettings $settings,
        BlogVkMessageBuilder $messageBuilder,
        BlogVkUrlBuilder $urlBuilder,
        VkWallClient $vkClient,
    ): void {
        Log::channel('queue')->info('PublishBlogPostToVkJob started', [
            'blog_post_id' => $this->blogPostId,
        ]);

        if ($reason = $settings->disabledReason()) {
            $this->abortWithError($reason);

            return;
        }

        /** @var BlogPost|null $post */
        $post = BlogPost::query()
            ->with(['category', 'socialPublications'])
            ->find($this->blogPostId);

        if (!$post) {
            Log::channel('queue')->warning('PublishBlogPostToVkJob: post not found', [
                'blog_post_id' => $this->blogPostId,
            ]);

            return;
        }

        $publication = BlogPostSocialPublication::query()
            ->where('blog_post_id', $post->id)
            ->where('platform', BlogPostSocialPublication::PLATFORM_VK)
            ->first();

        if (!$publication) {
            app(BlogVkPublicationCoordinator::class)->syncForPost($post);

            return;
        }

        if ($publication->status === BlogPostSocialPublication::STATUS_PUBLISHED) {
            Log::channel('queue')->info('PublishBlogPostToVkJob: already published', [
                'blog_post_id' => $post->id,
            ]);

            return;
        }

        if ($publication->status === BlogPostSocialPublication::STATUS_FAILED) {
            Log::channel('queue')->info('PublishBlogPostToVkJob: skipped (status failed, use retry)', [
                'blog_post_id' => $post->id,
            ]);

            return;
        }

        if (!$post->publish_to_vk) {
            $publication->update([
                'status' => BlogPostSocialPublication::STATUS_SKIPPED,
                'error_message' => null,
            ]);

            return;
        }

        if (!$post->isPubliclyVisible()) {
            $publication->update([
                'status' => BlogPostSocialPublication::STATUS_SKIPPED,
                'error_message' => 'Статья не опубликована на сайте.',
            ]);

            return;
        }

        if (empty($post->cover_image_path)) {
            $publication->update([
                'status' => BlogPostSocialPublication::STATUS_PENDING_COVER,
                'error_message' => null,
            ]);

            return;
        }

        $publication->update([
            'status' => BlogPostSocialPublication::STATUS_PUBLISHING,
            'attempts' => $publication->attempts + 1,
            'error_message' => null,
        ]);

        try {
            $publicUrl = $urlBuilder->build($post);
            $message = $messageBuilder->build($post, $publicUrl);

            $result = $vkClient->publishPost($message, $publicUrl, $post->cover_image_path);

            $externalId = sprintf('%d_%d', $result['owner_id'], $result['post_id']);

            $publication->update([
                'status' => BlogPostSocialPublication::STATUS_PUBLISHED,
                'external_post_id' => $externalId,
                'vk_message_snapshot' => $message,
                'error_message' => null,
                'published_at' => now(),
            ]);

            Log::channel('queue')->info('PublishBlogPostToVkJob published', [
                'blog_post_id' => $post->id,
                'external_post_id' => $externalId,
            ]);
        } catch (VkApiException|Throwable $e) {
            $this->markFailed($publication, $e->getMessage(), $e);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('queue')->error('PublishBlogPostToVkJob failed permanently', [
            'blog_post_id' => $this->blogPostId,
            'message' => $exception?->getMessage(),
        ]);

        $publication = BlogPostSocialPublication::query()
            ->where('blog_post_id', $this->blogPostId)
            ->where('platform', BlogPostSocialPublication::PLATFORM_VK)
            ->first();

        if ($publication && $publication->status !== BlogPostSocialPublication::STATUS_PUBLISHED) {
            $this->markFailed(
                $publication,
                $exception?->getMessage() ?? 'Неизвестная ошибка job',
                $exception ?? new \RuntimeException('Job failed'),
            );
        }
    }

    private function abortWithError(string $reason): void
    {
        $publication = BlogPostSocialPublication::query()
            ->where('blog_post_id', $this->blogPostId)
            ->where('platform', BlogPostSocialPublication::PLATFORM_VK)
            ->first();

        if ($publication) {
            $this->markFailed($publication, $reason, new \RuntimeException($reason));
        }

        Log::channel('queue')->error('PublishBlogPostToVkJob aborted', [
            'blog_post_id' => $this->blogPostId,
            'reason' => $reason,
        ]);
    }

    private function markFailed(
        BlogPostSocialPublication $publication,
        string $message,
        Throwable $e,
    ): void {
        $publication->update([
            'status' => BlogPostSocialPublication::STATUS_FAILED,
            'error_message' => mb_substr($message, 0, 2000, 'UTF-8'),
        ]);

        Log::channel('queue')->error('PublishBlogPostToVkJob error', [
            'blog_post_id' => $publication->blog_post_id,
            'attempts' => $publication->attempts,
            'error' => $e->getMessage(),
        ]);
    }
}
