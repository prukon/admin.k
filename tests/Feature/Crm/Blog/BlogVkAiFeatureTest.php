<?php

namespace Tests\Feature\Crm\Blog;

use App\Jobs\BlogVk\PublishBlogPostToVkJob;
use App\Models\BlogPost;
use App\Models\BlogPostSocialPublication;
use App\Models\Setting;
use App\Services\BlogVk\BlogVkAiMessageGenerator;
use App\Services\BlogVk\BlogVkDefaultPrompts;
use App\Services\BlogVk\BlogVkMessageBuilder;
use App\Services\BlogVk\BlogVkSettings;
use App\Services\BlogVk\BlogVkUrlBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Crm\Blog\Concerns\ConfiguresBlogVk;
use Tests\Feature\Crm\Blog\Concerns\ProvidesBlogSettingsPayload;

class BlogVkAiFeatureTest extends BlogAdminFeatureTestCase
{
    use ConfiguresBlogVk;
    use ProvidesBlogSettingsPayload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBlogVkEnabled();
    }

    public function test_settings_update_saves_vk_ai_enabled_and_prompt(): void
    {
        $prompt = trim(str_repeat('Уникальный промпт VK. ', 5));

        $res = $this->from(route('admin.blog.settings.edit'))
            ->post(route('admin.blog.settings.update'), $this->validBlogSettingsPayload([
                'vk_ai_enabled' => '0',
                'vk_ai_prompt_template' => $prompt,
            ]));

        $res->assertRedirect(route('admin.blog.settings.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('0', Setting::query()->where('name', 'blog.vk.ai_enabled')->whereNull('partner_id')->value('text'));
        $this->assertSame($prompt, Setting::query()->where('name', 'blog.vk.ai_prompt_template')->whereNull('partner_id')->value('text'));
    }

    public function test_settings_edit_page_shows_vk_ai_controls(): void
    {
        $this->get(route('admin.blog.settings.edit'))
            ->assertOk()
            ->assertSee('Текст VK через ИИ', escape: false)
            ->assertSee('Промпт ИИ для анонса VK', escape: false)
            ->assertSee('Шаблон текста для VK (fallback)', escape: false);
    }

    public function test_vk_settings_ai_enabled_respects_flags(): void
    {
        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.enabled', 'partner_id' => null],
            ['text' => '1']
        );
        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.ai_enabled', 'partner_id' => null],
            ['text' => '1']
        );

        $settings = app(BlogVkSettings::class);
        $this->assertTrue($settings->aiEnabled());

        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.ai_enabled', 'partner_id' => null],
            ['text' => '0']
        );
        $this->assertFalse(app(BlogVkSettings::class)->aiEnabled());

        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.enabled', 'partner_id' => null],
            ['text' => '0']
        );
        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.ai_enabled', 'partner_id' => null],
            ['text' => '1']
        );
        $this->assertFalse(app(BlogVkSettings::class)->aiEnabled());
    }

    public function test_vk_settings_default_ai_prompt_is_not_empty(): void
    {
        $prompt = app(BlogVkSettings::class)->aiPromptTemplate();

        $this->assertGreaterThan(50, mb_strlen($prompt, 'UTF-8'));
        $this->assertStringContainsString('friendly', mb_strtolower($prompt, 'UTF-8'));
        $this->assertSame(BlogVkDefaultPrompts::vkAiPromptTemplate(), $prompt);
    }

    public function test_ai_generator_returns_null_when_ai_disabled(): void
    {
        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.ai_enabled', 'partner_id' => null],
            ['text' => '0']
        );

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        $result = app(BlogVkAiMessageGenerator::class)->generate($post);

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_ai_generator_returns_null_when_daily_budget_exceeded(): void
    {
        $this->configureBlogVkAiEnabled();

        Setting::query()->updateOrCreate(
            ['name' => 'blog.ai.daily_budget_usd', 'partner_id' => null],
            ['text' => '0.001']
        );
        Setting::query()->updateOrCreate(
            ['name' => 'blog.ai.price_input_per_1m', 'partner_id' => null],
            ['text' => '2']
        );
        Setting::query()->updateOrCreate(
            ['name' => 'blog.ai.price_output_per_1m', 'partner_id' => null],
            ['text' => '8']
        );

        \App\Models\BlogAiDailyUsage::query()->updateOrCreate(
            ['date' => now()->toDateString()],
            ['reserved_usd' => 0, 'spent_usd' => 0.001, 'requests_count' => 1]
        );

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        $this->assertNull(app(BlogVkAiMessageGenerator::class)->generate($post));
    }

    public function test_ai_generator_returns_message_on_success(): void
    {
        $this->configureBlogVkAiEnabled();
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'api.openai.com/v1/responses*' => Http::response([
                'output_text' => json_encode(['message' => 'Дружелюбный анонс из ИИ.'], JSON_UNESCAPED_UNICODE),
                'usage' => ['input_tokens' => 50, 'output_tokens' => 40],
            ]),
        ]);

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk([
            'title' => 'Заголовок тестовой статьи',
            'excerpt' => 'Краткое описание для генерации.',
        ]));

        $message = app(BlogVkAiMessageGenerator::class)->generate($post);

        $this->assertSame('Дружелюбный анонс из ИИ.', $message);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/responses'));
    }

    public function test_ai_generator_returns_null_on_openai_error(): void
    {
        $this->configureBlogVkAiEnabled();
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'api.openai.com/v1/responses*' => Http::response([
                'error' => ['message' => 'Server error', 'type' => 'server_error'],
            ], 500),
        ]);

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk());

        $this->assertNull(app(BlogVkAiMessageGenerator::class)->generate($post));
    }

    public function test_job_falls_back_to_template_when_ai_disabled(): void
    {
        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.ai_enabled', 'partner_id' => null],
            ['text' => '0']
        );

        $this->fakeVkApiSuccess();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk([
            'title' => 'Заголовок fallback',
            'vk_message' => null,
            'excerpt' => 'Краткий excerpt',
        ]));

        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.message_template', 'partner_id' => null],
            ['text' => '{title} | {excerpt}']
        );

        BlogPostSocialPublication::query()->create([
            'blog_post_id' => $post->id,
            'platform' => BlogPostSocialPublication::PLATFORM_VK,
            'status' => BlogPostSocialPublication::STATUS_PENDING,
        ]);

        app()->call([new PublishBlogPostToVkJob($post->id), 'handle']);

        $post->refresh();
        $this->assertNull($post->vk_message);

        $pub = BlogPostSocialPublication::query()->where('blog_post_id', $post->id)->first();
        $this->assertStringContainsString('Заголовок fallback', (string) $pub->vk_message_snapshot);
        $this->assertStringContainsString('Краткий excerpt', (string) $pub->vk_message_snapshot);
    }

    public function test_message_builder_truncates_ai_result_to_400_chars(): void
    {
        $this->configureBlogVkAiEnabled();
        $long = str_repeat('а', 450);

        Http::fake([
            'api.openai.com/v1/responses*' => Http::response([
                'output_text' => json_encode(['message' => $long], JSON_UNESCAPED_UNICODE),
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ]),
        ]);
        config(['services.openai.api_key' => 'test-key']);

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk(['vk_message' => null]));
        $url = app(BlogVkUrlBuilder::class)->build($post);

        $message = app(BlogVkMessageBuilder::class)->build($post, $url);

        $this->assertSame(400, mb_strlen($message, 'UTF-8'));
    }

    public function test_settings_validates_vk_ai_prompt_min_length(): void
    {
        $res = $this->from(route('admin.blog.settings.edit'))
            ->post(route('admin.blog.settings.update'), $this->validBlogSettingsPayload([
                'vk_ai_prompt_template' => 'короткий',
            ]));

        $res->assertRedirect()
            ->assertSessionHasErrors(['vk_ai_prompt_template']);
    }

    public function test_coordinator_dispatches_job_for_post_without_vk_message(): void
    {
        Queue::fake();
        $this->configureBlogVkAiEnabled();

        $post = BlogPost::withoutEvents(fn () => $this->makePublishedBlogPostForVk([
            'vk_message' => null,
        ]));

        app(\App\Services\BlogVk\BlogVkPublicationCoordinator::class)->syncForPost(
            $post->fresh(['category', 'socialPublications'])
        );

        Queue::assertPushed(PublishBlogPostToVkJob::class);
    }
}
