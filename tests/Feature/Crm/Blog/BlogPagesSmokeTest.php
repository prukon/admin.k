<?php

namespace Tests\Feature\Crm\Blog;

use App\Models\BlogAiGeneration;
use App\Models\BlogAiGeneratedImage;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

class BlogPagesSmokeTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
    }

    public function test_public_blog_pages_open_with_200_when_data_exists(): void
    {
        $category = BlogCategory::query()->create([
            'name' => 'Оплаты',
            'slug' => 'payments-' . Str::lower(Str::random(8)),
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $post = BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Как принимать оплаты',
            'slug' => 'kak-prinimat-oplaty-' . Str::lower(Str::random(8)),
            'excerpt' => '<p>Коротко</p>',
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        auth()->logout();

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertViewIs('blog.index')
            ->assertSee('Блог', escape: false);

        $this->get(route('blog.category', $category->slug))
            ->assertOk()
            ->assertViewIs('blog.category')
            ->assertSee($category->name, escape: false);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertViewIs('blog.show')
            ->assertSee($post->title, escape: false);
    }

    public function test_public_blog_pages_return_404_for_missing_or_unpublished_entities(): void
    {
        $category = BlogCategory::query()->create([
            'name' => 'Категория',
            'slug' => 'cat-' . Str::lower(Str::random(8)),
        ]);

        $draft = BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Черновик',
            'slug' => 'draft-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
        ]);

        auth()->logout();

        $this->get(route('blog.category', 'missing-' . Str::lower(Str::random(8))))
            ->assertStatus(404);

        $this->get(route('blog.show', 'missing-' . Str::lower(Str::random(8))))
            ->assertStatus(404);

        // Неопубликованный пост не должен быть доступен публично
        $this->get(route('blog.show', $draft->slug))
            ->assertStatus(404);
    }

    public function test_admin_blog_pages_open_with_200(): void
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
            'action' => 'improve',
            'status' => 'queued',
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

        $this->get(route('admin.blog.posts.index'))
            ->assertOk()
            ->assertViewIs('admin.blog.posts.index')
            ->assertSee('Статьи', escape: false);

        $this->get(route('admin.blog.posts.create'))
            ->assertOk()
            ->assertViewIs('admin.blog.posts.create')
            ->assertSee('Новая статья', escape: false);

        $this->get(route('admin.blog.posts.edit', $post))
            ->assertOk()
            ->assertViewIs('admin.blog.posts.edit')
            ->assertSee('Редактирование статьи', escape: false);

        $this->get(route('admin.blog.categories.index'))
            ->assertOk()
            ->assertViewIs('admin.blog.categories.index')
            ->assertSee('Категории', escape: false);

        $this->get(route('admin.blog.categories.create'))
            ->assertOk()
            ->assertViewIs('admin.blog.categories.create')
            ->assertSee('Новая категория', escape: false);

        $this->get(route('admin.blog.categories.edit', $category))
            ->assertOk()
            ->assertViewIs('admin.blog.categories.edit')
            ->assertSee('Редактирование категории', escape: false);

        $this->get(route('admin.blog.settings.edit'))
            ->assertOk()
            ->assertViewIs('admin.blog.settings.edit')
            ->assertSee('SEO-настройки', escape: false);

        // status JSON endpoint
        $this->getJson(route('admin.blog.posts.ai.status', $gen))
            ->assertOk()
            ->assertJsonPath('id', $gen->id);

        // regenerate image JSON endpoint (smoke, validate 404 binding handled elsewhere)
        $this->postJson(route('admin.blog.posts.ai.images.regenerate', [$post, $img]), [])
            ->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonStructure(['generation_id', 'status']);
    }
}

