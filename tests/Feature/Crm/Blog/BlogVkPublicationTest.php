<?php

namespace Tests\Feature\Crm\Blog;

use App\Jobs\BlogVk\PublishBlogPostToVkJob;
use App\Models\BlogPost;
use App\Models\BlogPostSocialPublication;
use App\Models\Setting;
use App\Services\BlogVk\BlogVkMessageBuilder;
use App\Services\BlogVk\BlogVkPublicationCoordinator;
use App\Services\BlogVk\BlogVkUrlBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Crm\Blog\Concerns\ConfiguresBlogVk;

class BlogVkPublicationTest extends BlogAdminFeatureTestCase
{
    use ConfiguresBlogVk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBlogVkEnabled();
    }

    public function test_publishing_post_dispatches_vk_job(): void
    {
        Queue::fake();

        $category = $this->blogCategory();
        $this->useTmpPublicDisk();

        $post = BlogPost::withoutEvents(function () use ($category) {
            return BlogPost::query()->create([
                'blog_category_id' => $category->id,
                'title' => 'Черновик',
                'slug' => 'draft-' . Str::lower(Str::random(8)),
                'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
                'is_published' => false,
                'published_at' => now(),
                'publish_to_vk' => true,
            ]);
        });

        Storage::disk('public')->put('blog/covers/cover.jpg', 'bytes');

        $this->put(route('admin.blog.posts.update', $post), [
            'blog_category_id' => $category->id,
            'title' => $post->title,
            'content' => $post->content,
            'is_published' => true,
            'published_at' => now()->format('Y-m-d\TH:i'),
            'publish_to_vk' => 1,
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 1200, 630),
        ])->assertRedirect();

        Queue::assertPushed(PublishBlogPostToVkJob::class, fn ($job) => $job->blogPostId === $post->id);
    }

    public function test_store_defaults_publish_to_vk_true_and_saves_vk_message(): void
    {
        Queue::fake();
        $category = $this->blogCategory();

        $this->post(route('admin.blog.posts.store'), [
            'blog_category_id' => $category->id,
            'title' => 'Новая с VK',
            'slug' => 'new-vk-' . Str::lower(Str::random(6)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => null,
            'publish_to_vk' => 1,
            'vk_message' => 'Текст для соцсети',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $post = BlogPost::query()->where('title', 'Новая с VK')->firstOrFail();
        $this->assertTrue($post->publish_to_vk);
        $this->assertSame('Текст для соцсети', $post->vk_message);
    }

    public function test_update_validates_vk_message_max_length(): void
    {
        $category = $this->blogCategory();
        $post = BlogPost::withoutEvents(fn () => BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Валидация VK',
            'slug' => 'vk-val-' . Str::lower(Str::random(6)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
        ]));

        $this->from(route('admin.blog.posts.edit', $post))
            ->put(route('admin.blog.posts.update', $post), [
                'blog_category_id' => $category->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $post->content,
                'is_published' => false,
                'publish_to_vk' => 1,
                'vk_message' => str_repeat('а', 501),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['vk_message']);
    }

    public function test_without_cover_waits_for_cover_status(): void
    {
        Queue::fake();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk([
            'cover_image_path' => null,
        ]));

        app(BlogVkPublicationCoordinator::class)->syncForPost($post->fresh(['category', 'socialPublications']));

        Queue::assertNotPushed(PublishBlogPostToVkJob::class);

        $this->assertDatabaseHas('blog_post_social_publications', [
            'blog_post_id' => $post->id,
            'status' => BlogPostSocialPublication::STATUS_PENDING_COVER,
        ]);
    }

    public function test_cover_added_later_dispatches_vk_job(): void
    {
        Queue::fake();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk([
            'cover_image_path' => null,
        ]));

        app(BlogVkPublicationCoordinator::class)->syncForPost($post->fresh(['category', 'socialPublications']));
        Queue::assertNotPushed(PublishBlogPostToVkJob::class);

        $this->useTmpPublicDisk();
        $coverPath = 'blog/covers/late-' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($coverPath, 'bytes');

        $post->update(['cover_image_path' => $coverPath]);

        Queue::assertPushed(PublishBlogPostToVkJob::class, fn ($job) => $job->blogPostId === $post->id);
    }

    public function test_job_publishes_once_and_is_idempotent(): void
    {
        $this->fakeVkApiSuccess();
        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk([
            'vk_message' => 'Короткий текст для VK',
        ]));

        app(BlogVkPublicationCoordinator::class)->syncForPost($post->fresh(['category', 'socialPublications']));

        $job = new PublishBlogPostToVkJob($post->id);
        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('blog_post_social_publications', [
            'blog_post_id' => $post->id,
            'status' => BlogPostSocialPublication::STATUS_PUBLISHED,
            'external_post_id' => '-123456_999',
            'vk_message_snapshot' => 'Короткий текст для VK',
        ]);

        app()->call([$job, 'handle']);

        $this->assertEquals(1, BlogPostSocialPublication::query()
            ->where('blog_post_id', $post->id)
            ->where('status', BlogPostSocialPublication::STATUS_PUBLISHED)
            ->count());
    }

    public function test_scheduled_post_stays_pending_schedule_until_due(): void
    {
        Queue::fake();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk([
            'published_at' => now()->addDay(),
        ]));

        app(BlogVkPublicationCoordinator::class)->syncForPost($post->fresh(['category', 'socialPublications']));

        Queue::assertNotPushed(PublishBlogPostToVkJob::class);

        $this->assertDatabaseHas('blog_post_social_publications', [
            'blog_post_id' => $post->id,
            'status' => BlogPostSocialPublication::STATUS_PENDING_SCHEDULE,
        ]);
    }

    public function test_command_dispatches_job_when_scheduled_time_arrives(): void
    {
        Queue::fake();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk([
            'published_at' => now()->subMinute(),
        ]));

        BlogPostSocialPublication::query()->create([
            'blog_post_id' => $post->id,
            'platform' => BlogPostSocialPublication::PLATFORM_VK,
            'status' => BlogPostSocialPublication::STATUS_PENDING_SCHEDULE,
        ]);

        Artisan::call('blog:process-vk-publications');

        Queue::assertPushed(PublishBlogPostToVkJob::class, fn ($job) => $job->blogPostId === $post->id);
    }

    public function test_command_processes_pending_cover_when_cover_exists(): void
    {
        Queue::fake();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        BlogPostSocialPublication::query()->create([
            'blog_post_id' => $post->id,
            'platform' => BlogPostSocialPublication::PLATFORM_VK,
            'status' => BlogPostSocialPublication::STATUS_PENDING_COVER,
        ]);

        Artisan::call('blog:process-vk-publications');

        Queue::assertPushed(PublishBlogPostToVkJob::class, fn ($job) => $job->blogPostId === $post->id);
    }

    public function test_publish_to_vk_disabled_skips_publication(): void
    {
        Queue::fake();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk([
            'publish_to_vk' => false,
        ]));

        app(BlogVkPublicationCoordinator::class)->syncForPost($post->fresh(['category', 'socialPublications']));

        Queue::assertNotPushed(PublishBlogPostToVkJob::class);

        $this->assertDatabaseHas('blog_post_social_publications', [
            'blog_post_id' => $post->id,
            'status' => BlogPostSocialPublication::STATUS_SKIPPED,
        ]);
    }

    public function test_globally_disabled_in_env_does_not_dispatch(): void
    {
        Queue::fake();
        config(['services.vk.enabled' => false]);

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        app(BlogVkPublicationCoordinator::class)->syncForPost($post->fresh(['category', 'socialPublications']));

        Queue::assertNotPushed(PublishBlogPostToVkJob::class);
        $this->assertDatabaseMissing('blog_post_social_publications', [
            'blog_post_id' => $post->id,
        ]);
    }

    public function test_admin_vk_setting_disabled_does_not_dispatch(): void
    {
        Queue::fake();
        $this->configureBlogVkDisabledInAdmin();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        app(BlogVkPublicationCoordinator::class)->syncForPost($post->fresh(['category', 'socialPublications']));

        Queue::assertNotPushed(PublishBlogPostToVkJob::class);
    }

    public function test_vk_api_error_marks_publication_as_failed(): void
    {
        $this->fakeVkApiWallPostError();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        BlogPostSocialPublication::query()->create([
            'blog_post_id' => $post->id,
            'platform' => BlogPostSocialPublication::PLATFORM_VK,
            'status' => BlogPostSocialPublication::STATUS_PENDING,
        ]);

        try {
            app()->call([new PublishBlogPostToVkJob($post->id), 'handle']);
        } catch (\Throwable) {
            // Job rethrows for queue retries.
        }

        $this->assertDatabaseHas('blog_post_social_publications', [
            'blog_post_id' => $post->id,
            'status' => BlogPostSocialPublication::STATUS_FAILED,
        ]);

        $pub = BlogPostSocialPublication::query()->where('blog_post_id', $post->id)->first();
        $this->assertNotEmpty($pub->error_message);
    }

    public function test_message_builder_uses_template_and_url_with_utm(): void
    {
        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.message_template', 'partner_id' => null],
            ['text' => "{title} | {url}"]
        );

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk([
            'vk_message' => null,
        ]));

        $url = app(BlogVkUrlBuilder::class)->build($post);
        $message = app(BlogVkMessageBuilder::class)->build($post, $url);

        $this->assertStringContainsString($post->title, $message);
        $this->assertStringContainsString('utm_source=vk', $message);
        $this->assertStringContainsString('utm_medium=social', $message);
        $this->assertStringContainsString('utm_campaign=blog', $message);
    }

    public function test_republishing_on_site_does_not_dispatch_second_vk_job(): void
    {
        Queue::fake();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        BlogPostSocialPublication::query()->create([
            'blog_post_id' => $post->id,
            'platform' => BlogPostSocialPublication::PLATFORM_VK,
            'status' => BlogPostSocialPublication::STATUS_PUBLISHED,
            'external_post_id' => '-123_1',
            'published_at' => now()->subDay(),
        ]);

        $post->update(['title' => 'Обновлённый заголовок']);

        Queue::assertNotPushed(PublishBlogPostToVkJob::class);
    }

    public function test_retry_route_queues_job_for_failed_publication(): void
    {
        Queue::fake();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        BlogPostSocialPublication::query()->create([
            'blog_post_id' => $post->id,
            'platform' => BlogPostSocialPublication::PLATFORM_VK,
            'status' => BlogPostSocialPublication::STATUS_FAILED,
            'error_message' => 'test',
        ]);

        $this->post(route('admin.blog.posts.vk.retry', $post))
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(PublishBlogPostToVkJob::class);
    }

    public function test_retry_does_not_queue_when_already_published_in_vk(): void
    {
        Queue::fake();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        BlogPostSocialPublication::query()->create([
            'blog_post_id' => $post->id,
            'platform' => BlogPostSocialPublication::PLATFORM_VK,
            'status' => BlogPostSocialPublication::STATUS_PUBLISHED,
            'external_post_id' => '-1_1',
            'published_at' => now(),
        ]);

        app(BlogVkPublicationCoordinator::class)->retry($post->fresh(['category', 'socialPublications']));

        Queue::assertNotPushed(PublishBlogPostToVkJob::class);
    }

    public function test_settings_update_saves_vk_template(): void
    {
        $res = $this->from(route('admin.blog.settings.edit'))
            ->post(route('admin.blog.settings.update'), [
                'vk_enabled' => '1',
                'vk_message_template' => "{title}\n{url}",
                'ai_prompt_template' => str_repeat('A', 60),
            ]);

        $res->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            "{title}\n{url}",
            Setting::query()->where('name', 'blog.vk.message_template')->whereNull('partner_id')->value('text')
        );
    }

    public function test_unpublishing_post_does_not_remove_vk_publication_record(): void
    {
        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        BlogPostSocialPublication::query()->create([
            'blog_post_id' => $post->id,
            'platform' => BlogPostSocialPublication::PLATFORM_VK,
            'status' => BlogPostSocialPublication::STATUS_PUBLISHED,
            'external_post_id' => '-1_99',
            'published_at' => now(),
        ]);

        $post->update(['is_published' => false]);

        $this->assertDatabaseHas('blog_post_social_publications', [
            'blog_post_id' => $post->id,
            'status' => BlogPostSocialPublication::STATUS_PUBLISHED,
        ]);
    }
}
