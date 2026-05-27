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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishBlogPostToVkJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 1800, 3600];

    public function __construct(
        public readonly int $blogPostId,
    ) {
    }

    public function uniqueId(): string
    {
        return 'blog-vk-publish-' . $this->blogPostId;
    }

    public function handle(
        BlogVkSettings $settings,
        BlogVkMessageBuilder $messageBuilder,
        BlogVkUrlBuilder $urlBuilder,
        VkWallClient $vkClient,
        BlogVkPublicationCoordinator $coordinator,
    ): void {
        if (!$settings->globallyEnabled()) {
            return;
        }

        /** @var BlogPost|null $post */
        $post = BlogPost::query()
            ->with(['category', 'socialPublications'])
            ->find($this->blogPostId);

        if (!$post) {
            return;
        }

        $publication = BlogPostSocialPublication::query()
            ->where('blog_post_id', $post->id)
            ->where('platform', BlogPostSocialPublication::PLATFORM_VK)
            ->first();

        if (!$publication) {
            $coordinator->syncForPost($post);

            return;
        }

        if ($publication->status === BlogPostSocialPublication::STATUS_PUBLISHED) {
            return;
        }

        if (!$post->publish_to_vk || !$post->isPubliclyVisible()) {
            $coordinator->syncForPost($post);

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

            $result = $vkClient->publishPost($message, $publicUrl);

            $externalId = sprintf('%d_%d', $result['owner_id'], $result['post_id']);

            $publication->update([
                'status' => BlogPostSocialPublication::STATUS_PUBLISHED,
                'external_post_id' => $externalId,
                'vk_message_snapshot' => $message,
                'error_message' => null,
                'published_at' => now(),
            ]);
        } catch (VkApiException $e) {
            $this->markFailed($publication, $e->getMessage(), $e);

            throw $e;
        } catch (Throwable $e) {
            $this->markFailed($publication, $e->getMessage(), $e);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('stack')->warning('PublishBlogPostToVkJob failed permanently', [
            'blog_post_id' => $this->blogPostId,
            'message' => $exception?->getMessage(),
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

        Log::warning('VK publication failed', [
            'blog_post_id' => $publication->blog_post_id,
            'attempts' => $publication->attempts,
            'error' => $e->getMessage(),
        ]);
    }
}
