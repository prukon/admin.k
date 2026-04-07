<?php

namespace Tests\Feature\Crm\Blog;

use App\Models\BlogAiGeneratedImage;
use App\Models\BlogAiGeneration;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Str;
class BlogAdmin404AndEmptyStateTest extends BlogAdminFeatureTestCase
{
    public function test_admin_pages_open_with_200_on_empty_database(): void
    {
        $this->get(route('admin.blog.posts.index'))
            ->assertOk()
            ->assertViewIs('admin.blog.posts.index');

        $this->get(route('admin.blog.posts.create'))
            ->assertOk()
            ->assertViewIs('admin.blog.posts.create');

        $this->get(route('admin.blog.categories.index'))
            ->assertOk()
            ->assertViewIs('admin.blog.categories.index');

        $this->get(route('admin.blog.categories.create'))
            ->assertOk()
            ->assertViewIs('admin.blog.categories.create');

        $this->get(route('admin.blog.settings.edit'))
            ->assertOk()
            ->assertViewIs('admin.blog.settings.edit');
    }

    public function test_admin_category_routes_return_404_for_missing_category(): void
    {
        $missingId = 999999;

        $this->get(route('admin.blog.categories.edit', $missingId))->assertStatus(404);
        $this->put(route('admin.blog.categories.update', $missingId), [])->assertStatus(404);
        $this->delete(route('admin.blog.categories.destroy', $missingId))->assertStatus(404);
    }

    public function test_admin_post_routes_return_404_for_missing_post(): void
    {
        $missingId = 999999;

        $this->get(route('admin.blog.posts.edit', $missingId))->assertStatus(404);
        $this->put(route('admin.blog.posts.update', $missingId), [])->assertStatus(404);
        $this->delete(route('admin.blog.posts.destroy', $missingId))->assertStatus(404);
    }

    public function test_ai_routes_return_404_for_missing_models(): void
    {
        $missingId = 999999;

        $this->getJson(route('admin.blog.posts.ai.status', $missingId))->assertStatus(404);

        $this->postJson(route('admin.blog.posts.ai.post.start', $missingId), [
            'action' => 'improve',
            'prompt' => 'ok',
        ])->assertStatus(404);

        // for regenerate, both post and image are model-bound; missing image should 404
        $category = BlogCategory::query()->create([
            'name' => 'Категория',
            'slug' => 'cat-' . Str::lower(Str::random(8)),
        ]);
        $post = BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Статья',
            'slug' => 'post-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
        ]);

        $this->postJson(route('admin.blog.posts.ai.images.regenerate', [$post, $missingId]), [])
            ->assertStatus(404);
    }

    public function test_admin_edit_pages_return_200_for_existing_models_and_do_not_500(): void
    {
        $category = BlogCategory::query()->create([
            'name' => 'Категория',
            'slug' => 'cat-' . Str::lower(Str::random(8)),
        ]);

        $post = BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Статья',
            'slug' => 'post-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
        ]);

        $gen = BlogAiGeneration::query()->create([
            'user_id' => $this->user->id,
            'blog_post_id' => $post->id,
            'blog_category_id' => $category->id,
            'action' => 'new_post',
            'status' => 'queued',
            'budget_date' => now()->toDateString(),
            'prompt_user' => '—',
            'prompt_template_snapshot' => 'tpl',
            'model' => 'gpt-5.1',
            'max_output_tokens' => 2000,
        ]);

        BlogAiGeneratedImage::query()->create([
            'blog_ai_generation_id' => $gen->id,
            'blog_post_id' => $post->id,
            'kind' => 'inline',
            'aspect' => '4:3',
            'prompt' => 'prompt',
            'status' => 'succeeded',
        ]);

        $this->get(route('admin.blog.categories.edit', $category))
            ->assertOk()
            ->assertViewIs('admin.blog.categories.edit');

        $this->get(route('admin.blog.posts.edit', $post))
            ->assertOk()
            ->assertViewIs('admin.blog.posts.edit');

        $this->getJson(route('admin.blog.posts.ai.status', $gen))
            ->assertOk()
            ->assertJsonPath('id', $gen->id);
    }
}

