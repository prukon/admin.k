<?php

namespace Tests\Feature\Crm\Blog\Ai;

use App\Jobs\BlogAi\RunBlogAiGenerationJob;
use App\Jobs\BlogAi\RunBlogAiImageRegenerationJob;
use App\Models\BlogAiDailyUsage;
use App\Models\BlogAiGeneratedImage;
use App\Models\BlogAiGeneration;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Feature\Crm\Blog\BlogAdminFeatureTestCase;

class BlogAiRequestsTest extends BlogAdminFeatureTestCase
{
    private function setGlobalSetting(string $name, ?string $text): void
    {
        Setting::query()->updateOrCreate(
            ['name' => $name, 'partner_id' => null],
            ['text' => $text]
        );
    }

    private function category(): BlogCategory
    {
        return BlogCategory::query()->create([
            'name' => 'Категория',
            'slug' => 'cat-' . Str::lower(Str::random(8)),
        ]);
    }

    private function makePost(BlogCategory $category): BlogPost
    {
        return BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Статья',
            'slug' => 'post-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
        ]);
    }

    public function test_start_new_post_validates_and_returns_json_errors(): void
    {
        $res = $this->postJson(route('admin.blog.posts.ai.start'), [
            'blog_category_id' => 999999,
            'prompt' => 'short',
            'want_cover_image' => 'x',
            'inline_images_count' => 10,
        ]);

        $res->assertStatus(422);
        $res->assertJsonStructure(['message', 'errors']);
        $this->assertNotSame('', (string) $res->json('message'));

        $res->assertJsonPath('errors.blog_category_id.0', 'Выберите корректную категорию.');
        $this->assertNotEmpty($res->json('errors.prompt.0'));
        $res->assertJsonPath('errors.inline_images_count.0', 'Можно добавить не более 3 изображений внутри статьи.');
    }

    public function test_start_new_post_returns_422_when_cannot_estimate_cost_without_token_prices(): void
    {
        $this->setGlobalSetting('blog.ai.daily_budget_usd', '5');
        $this->setGlobalSetting('blog.ai.price_input_per_1m', null);
        $this->setGlobalSetting('blog.ai.price_output_per_1m', null);

        $cat = $this->category();

        $res = $this->postJson(route('admin.blog.posts.ai.start'), [
            'blog_category_id' => $cat->id,
            'prompt' => str_repeat('Детали ', 5), // >= 20 chars
            'want_cover_image' => false,
            'inline_images_count' => 0,
        ]);

        $res->assertStatus(422);
        $res->assertJsonStructure(['message', 'errors']);
        $this->assertStringContainsString('Невозможно оценить стоимость', (string) $res->json('message'));
        $this->assertNotEmpty($res->json('errors.prompt.0'));
    }

    public function test_start_new_post_returns_422_when_images_requested_but_disabled(): void
    {
        $this->setGlobalSetting('blog.ai.images.enabled', '0');

        $cat = $this->category();

        $res = $this->postJson(route('admin.blog.posts.ai.start'), [
            'blog_category_id' => $cat->id,
            'prompt' => str_repeat('Детали ', 5),
            'want_cover_image' => true,
            'inline_images_count' => 0,
        ]);

        $res->assertStatus(422);
        $res->assertJsonStructure(['message', 'errors']);
        $res->assertJsonPath('message', 'Генерация изображений отключена в настройках блога.');
        $res->assertJsonPath('errors.prompt.0', 'Генерация изображений отключена в настройках блога.');
    }

    public function test_start_new_post_success_dispatches_job_when_budget_is_zero(): void
    {
        Queue::fake();

        $this->setGlobalSetting('blog.ai.daily_budget_usd', '0');

        $cat = $this->category();

        $res = $this->postJson(route('admin.blog.posts.ai.start'), [
            'blog_category_id' => $cat->id,
            'prompt' => str_repeat('Детали ', 5),
            'want_cover_image' => false,
            'inline_images_count' => 0,
        ]);

        $res->assertOk();
        $res->assertJsonStructure(['generation_id', 'status']);
        $res->assertJsonPath('status', 'queued');

        $genId = (int) $res->json('generation_id');
        $this->assertGreaterThan(0, $genId);

        $this->assertDatabaseHas('blog_ai_generations', [
            'id' => $genId,
            'action' => 'new_post',
            'status' => 'queued',
            'blog_category_id' => $cat->id,
            'user_id' => $this->user->id,
        ]);

        Queue::assertPushed(RunBlogAiGenerationJob::class, function (RunBlogAiGenerationJob $job) use ($genId) {
            return (int) $job->generationId === $genId;
        });
    }

    public function test_start_for_post_validates_action(): void
    {
        $this->setGlobalSetting('blog.ai.daily_budget_usd', '0');

        $cat = $this->category();
        $post = $this->makePost($cat);

        $res = $this->postJson(route('admin.blog.posts.ai.post.start', $post), [
            'action' => 'wrong',
            'prompt' => 'ok',
        ]);

        $res->assertStatus(422);
        $res->assertJsonStructure(['message', 'errors']);
        $this->assertNotEmpty($res->json('errors.action.0'));
    }

    public function test_start_for_post_returns_422_when_daily_budget_exceeded_and_message_is_in_errors_prompt(): void
    {
        $this->setGlobalSetting('blog.ai.daily_budget_usd', '0.0001');
        $this->setGlobalSetting('blog.ai.price_input_per_1m', '100000');
        $this->setGlobalSetting('blog.ai.price_output_per_1m', '100000');
        $this->setGlobalSetting('blog.ai.max_output_tokens', '8000');

        $cat = $this->category();
        $post = $this->makePost($cat);

        $res = $this->postJson(route('admin.blog.posts.ai.post.start', $post), [
            'action' => 'improve',
            'prompt' => str_repeat('Детали ', 5),
        ]);

        $res->assertStatus(422);
        $res->assertJsonStructure(['message', 'errors']);
        $this->assertStringContainsString('Превышен дневной лимит ИИ', (string) $res->json('message'));
        $this->assertNotEmpty($res->json('errors.prompt.0'));
    }

    public function test_status_returns_progress_fallback_and_edit_url_when_post_present(): void
    {
        $cat = $this->category();
        $post = $this->makePost($cat);

        $gen = BlogAiGeneration::query()->create([
            'user_id' => $this->user->id,
            'blog_post_id' => $post->id,
            'blog_category_id' => $cat->id,
            'action' => 'improve',
            'status' => 'queued',
            'budget_date' => now()->toDateString(),
            'progress' => 0,
            'phase' => 'text',
            'prompt_user' => '—',
            'prompt_template_snapshot' => 'tpl',
            'model' => 'gpt-5.1',
            'max_output_tokens' => 2000,
        ]);

        $res = $this->getJson(route('admin.blog.posts.ai.status', $gen))
            ->assertOk()
            ->assertJsonPath('id', $gen->id)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('progress', 15)
            ->assertJsonPath('blog_post_id', $post->id);

        $res->assertJsonPath('edit_url', route('admin.blog.posts.edit', $post->id));
    }

    public function test_status_watchdog_marks_hung_generation_as_failed_and_finalizes_budget_and_images(): void
    {
        $this->setGlobalSetting('blog.ai.daily_budget_usd', '5');

        $cat = $this->category();
        $post = $this->makePost($cat);

        $budgetDate = now()->toDateString();

        /** @var BlogAiDailyUsage $usage */
        $usage = BlogAiDailyUsage::query()->create([
            'date' => $budgetDate,
            'reserved_usd' => 0.5000,
            'spent_usd' => 0,
            'reserved_input_tokens' => 0,
            'reserved_output_tokens' => 0,
            'spent_input_tokens' => 0,
            'spent_output_tokens' => 0,
            'requests_count' => 1,
        ]);

        $gen = BlogAiGeneration::query()->create([
            'user_id' => $this->user->id,
            'blog_post_id' => $post->id,
            'blog_category_id' => $cat->id,
            'action' => 'improve',
            'status' => 'running',
            'budget_date' => $budgetDate,
            'progress' => 0,
            'phase' => 'text',
            'prompt_user' => '—',
            'prompt_template_snapshot' => 'tpl',
            'model' => 'gpt-5.1',
            'max_output_tokens' => 2000,
            'reserved_usd' => 0.5000,
            'started_at' => now()->subMinutes(6),
        ]);

        $imgQueued = BlogAiGeneratedImage::query()->create([
            'blog_ai_generation_id' => $gen->id,
            'blog_post_id' => $post->id,
            'kind' => 'inline',
            'aspect' => '4:3',
            'prompt' => 'prompt',
            'status' => 'queued',
        ]);

        $imgRunning = BlogAiGeneratedImage::query()->create([
            'blog_ai_generation_id' => $gen->id,
            'blog_post_id' => $post->id,
            'kind' => 'cover',
            'aspect' => 'og',
            'prompt' => 'prompt',
            'status' => 'running',
        ]);

        $res = $this->getJson(route('admin.blog.posts.ai.status', $gen))
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('progress', 100)
            ->assertJsonPath('phase', 'done');

        $this->assertStringContainsString('Задача генерации зависла', (string) $res->json('error_message'));

        $gen->refresh();
        $this->assertSame('failed', (string) $gen->status);
        $this->assertNotNull($gen->finished_at);
        $this->assertNotSame('', (string) ($gen->error_message ?? ''));

        $imgQueued->refresh();
        $imgRunning->refresh();
        $this->assertSame('failed', (string) $imgQueued->status);
        $this->assertSame('failed', (string) $imgRunning->status);
        $this->assertNotSame('', (string) ($imgQueued->error_message ?? ''));

        $usage->refresh();
        $this->assertSame(0.0, (float) $usage->reserved_usd);
    }

    public function test_regenerate_image_returns_404_when_image_does_not_belong_to_post(): void
    {
        $cat = $this->category();
        $postA = $this->makePost($cat);
        $postB = $this->makePost($cat);

        $gen = BlogAiGeneration::query()->create([
            'user_id' => $this->user->id,
            'blog_post_id' => $postB->id,
            'blog_category_id' => $cat->id,
            'action' => 'new_post',
            'status' => 'succeeded',
            'budget_date' => now()->toDateString(),
            'prompt_user' => '—',
            'prompt_template_snapshot' => 'tpl',
            'model' => 'gpt-5.1',
            'max_output_tokens' => 2000,
        ]);

        $img = BlogAiGeneratedImage::query()->create([
            'blog_ai_generation_id' => $gen->id,
            'blog_post_id' => $postB->id,
            'kind' => 'inline',
            'aspect' => '4:3',
            'prompt' => 'prompt',
            'status' => 'succeeded',
        ]);

        $this->postJson(route('admin.blog.posts.ai.images.regenerate', [$postA, $img]), [])
            ->assertStatus(404);
    }

    public function test_regenerate_image_returns_422_when_images_disabled_and_error_is_under_prompt_extra(): void
    {
        $this->setGlobalSetting('blog.ai.images.enabled', '0');

        $cat = $this->category();
        $post = $this->makePost($cat);

        $gen = BlogAiGeneration::query()->create([
            'user_id' => $this->user->id,
            'blog_post_id' => $post->id,
            'blog_category_id' => $cat->id,
            'action' => 'new_post',
            'status' => 'succeeded',
            'budget_date' => now()->toDateString(),
            'prompt_user' => '—',
            'prompt_template_snapshot' => 'tpl',
            'model' => 'gpt-5.1',
            'max_output_tokens' => 2000,
        ]);

        $img = BlogAiGeneratedImage::query()->create([
            'blog_ai_generation_id' => $gen->id,
            'blog_post_id' => $post->id,
            'kind' => 'cover',
            'aspect' => 'og',
            'prompt' => 'prompt',
            'status' => 'succeeded',
        ]);

        $res = $this->postJson(route('admin.blog.posts.ai.images.regenerate', [$post, $img]), [
            'prompt_extra' => 'more',
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('message', 'Генерация изображений отключена в настройках блога.');
        $res->assertJsonPath('errors.prompt_extra.0', 'Генерация изображений отключена в настройках блога.');
    }

    public function test_regenerate_image_returns_422_when_budget_exceeded(): void
    {
        $this->setGlobalSetting('blog.ai.daily_budget_usd', '0.005'); // lower than default inline cost 0.01
        $this->setGlobalSetting('blog.ai.images.enabled', '1');

        $cat = $this->category();
        $post = $this->makePost($cat);

        $gen = BlogAiGeneration::query()->create([
            'user_id' => $this->user->id,
            'blog_post_id' => $post->id,
            'blog_category_id' => $cat->id,
            'action' => 'new_post',
            'status' => 'succeeded',
            'budget_date' => now()->toDateString(),
            'prompt_user' => '—',
            'prompt_template_snapshot' => 'tpl',
            'model' => 'gpt-5.1',
            'max_output_tokens' => 2000,
        ]);

        $img = BlogAiGeneratedImage::query()->create([
            'blog_ai_generation_id' => $gen->id,
            'blog_post_id' => $post->id,
            'kind' => 'inline',
            'aspect' => '4:3',
            'prompt' => 'prompt',
            'status' => 'succeeded',
        ]);

        $res = $this->postJson(route('admin.blog.posts.ai.images.regenerate', [$post, $img]), [
            'prompt_extra' => 'more',
        ]);

        $res->assertStatus(422);
        $res->assertJsonStructure(['message', 'errors']);
        $this->assertStringContainsString('Превышен дневной лимит ИИ', (string) $res->json('message'));
        $this->assertNotEmpty($res->json('errors.prompt_extra.0'));
    }

    public function test_regenerate_image_success_dispatches_job_and_creates_generation_row(): void
    {
        Queue::fake();

        $this->setGlobalSetting('blog.ai.daily_budget_usd', '5');
        $this->setGlobalSetting('blog.ai.images.enabled', '1');

        $cat = $this->category();
        $post = $this->makePost($cat);

        $gen = BlogAiGeneration::query()->create([
            'user_id' => $this->user->id,
            'blog_post_id' => $post->id,
            'blog_category_id' => $cat->id,
            'action' => 'new_post',
            'status' => 'succeeded',
            'budget_date' => now()->toDateString(),
            'prompt_user' => '—',
            'prompt_template_snapshot' => 'tpl',
            'model' => 'gpt-5.1',
            'max_output_tokens' => 2000,
        ]);

        $img = BlogAiGeneratedImage::query()->create([
            'blog_ai_generation_id' => $gen->id,
            'blog_post_id' => $post->id,
            'kind' => 'cover',
            'aspect' => 'og',
            'prompt' => 'prompt',
            'status' => 'succeeded',
        ]);

        $res = $this->postJson(route('admin.blog.posts.ai.images.regenerate', [$post, $img]), [
            'prompt_extra' => 'make it better',
        ]);

        $res->assertOk();
        $res->assertJsonStructure(['generation_id', 'status']);
        $res->assertJsonPath('status', 'queued');

        $newGenId = (int) $res->json('generation_id');
        $this->assertGreaterThan(0, $newGenId);

        $this->assertDatabaseHas('blog_ai_generations', [
            'id' => $newGenId,
            'action' => 'image_regen',
            'status' => 'queued',
            'blog_post_id' => $post->id,
            'blog_ai_generated_image_id' => $img->id,
        ]);

        Queue::assertPushed(RunBlogAiImageRegenerationJob::class, function (RunBlogAiImageRegenerationJob $job) use ($newGenId) {
            return (int) $job->generationId === $newGenId;
        });
    }
}

