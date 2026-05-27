<?php

namespace Tests\Feature\Crm\Blog;

use App\Models\BlogAiGeneration;
use App\Models\BlogAiGeneratedImage;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Полный доступ к разделу «Блог» в админке: при blog.view — страницы и endpoint'ы отвечают успешно.
 */
final class BlogSectionFullAccessFeatureTest extends BlogAdminFeatureTestCase
{
    private BlogCategory $category;

    private BlogPost $post;

    private BlogAiGeneration $generation;

    private BlogAiGeneratedImage $image;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = BlogCategory::query()->create([
            'name' => 'Доступ блог',
            'slug' => 'blog-access-' . Str::lower(Str::random(6)),
        ]);

        $this->post = BlogPost::query()->create([
            'blog_category_id' => $this->category->id,
            'title' => 'Статья доступ',
            'slug' => 'post-access-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 25) . '</p>',
            'is_published' => true,
            'published_at' => now()->subHour(),
        ]);

        $this->generation = BlogAiGeneration::query()->create([
            'user_id' => $this->user->id,
            'blog_post_id' => $this->post->id,
            'blog_category_id' => $this->category->id,
            'action' => 'improve',
            'status' => 'queued',
            'budget_date' => now()->toDateString(),
            'prompt_user' => '—',
            'prompt_template_snapshot' => 'tpl',
            'model' => 'gpt-5.1',
            'max_output_tokens' => 2000,
        ]);

        $this->image = BlogAiGeneratedImage::query()->create([
            'blog_ai_generation_id' => $this->generation->id,
            'blog_post_id' => $this->post->id,
            'kind' => 'inline',
            'aspect' => '4:3',
            'prompt' => 'prompt',
            'status' => 'succeeded',
        ]);
    }

    public function test_guest_is_redirected_from_blog_admin_pages(): void
    {
        Auth::logout();

        foreach ($this->getRoutes() as $item) {
            if (($item['expect'] ?? 'redirect') !== 'redirect') {
                continue;
            }

            $response = $this->call($item['method'], $item['url'], $item['data'] ?? []);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']}"
            );
        }
    }

    public function test_user_without_blog_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('blog.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        foreach ($this->getRoutes() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? []
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без blog.view: {$item['method']} {$item['url']}"
            );
        }
    }

    public function test_admin_with_blog_view_gets_successful_responses(): void
    {
        foreach ($this->getRoutes() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? []
            );

            $expected = $item['expect'] ?? 'ok';

            if ($expected === 'ok') {
                $response->assertOk();
                if (!empty($item['view'])) {
                    $response->assertViewIs($item['view']);
                }
                if (!empty($item['json_path'])) {
                    $response->assertJsonPath($item['json_path'][0], $item['json_path'][1]);
                }
            } elseif ($expected === 'redirect') {
                $response->assertRedirect();
            }
        }
    }

    /**
     * @return list<array{
     *     method: string,
     *     url: string,
     *     data?: array<string, mixed>,
     *     headers?: array<string, string>,
     *     view?: string,
     *     expect?: string,
     *     json_path?: array{0: string, 1: mixed}
     * }>
     */
    private function getRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.blog.posts.index'),
                'view' => 'admin.blog.posts.index',
                'expect' => 'ok',
            ],
            [
                'method' => 'GET',
                'url' => route('admin.blog.posts.create'),
                'view' => 'admin.blog.posts.create',
                'expect' => 'ok',
            ],
            [
                'method' => 'GET',
                'url' => route('admin.blog.posts.edit', $this->post),
                'view' => 'admin.blog.posts.edit',
                'expect' => 'ok',
            ],
            [
                'method' => 'GET',
                'url' => route('admin.blog.categories.index'),
                'view' => 'admin.blog.categories.index',
                'expect' => 'ok',
            ],
            [
                'method' => 'GET',
                'url' => route('admin.blog.categories.create'),
                'view' => 'admin.blog.categories.create',
                'expect' => 'ok',
            ],
            [
                'method' => 'GET',
                'url' => route('admin.blog.categories.edit', $this->category),
                'view' => 'admin.blog.categories.edit',
                'expect' => 'ok',
            ],
            [
                'method' => 'GET',
                'url' => route('admin.blog.settings.edit'),
                'view' => 'admin.blog.settings.edit',
                'expect' => 'ok',
            ],
            [
                'method' => 'GET',
                'url' => route('admin.blog.posts.ai.status', $this->generation),
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
                'expect' => 'ok',
                'json_path' => ['id', $this->generation->id],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.blog.posts.ai.images.regenerate', [$this->post, $this->image]),
                'headers' => ['HTTP_ACCEPT' => 'application/json'],
                'data' => [],
                'expect' => 'ok',
                'json_path' => ['status', 'queued'],
            ],
            [
                'method' => 'POST',
                'url' => route('admin.blog.posts.vk.retry', $this->post),
                'expect' => 'redirect',
            ],
        ];
    }
}
