<?php

namespace Tests\Feature\Crm\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostSocialPublication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\Crm\Blog\Concerns\ConfiguresBlogVk;
use Tests\Feature\Crm\Blog\Concerns\ProvidesBlogSettingsPayload;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к VK-функционалу блога и связанным страницам админки:
 * с правом blog.view — успешные ответы, без права — 403, гость — 302/401.
 */
final class BlogVkSectionFullAccessFeatureTest extends CrmTestCase
{
    use ConfiguresBlogVk;
    use ProvidesBlogSettingsPayload;

    private BlogPost $blogPost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBlogVkEnabled();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $category = BlogCategory::query()->create([
            'name' => 'VK доступ',
            'slug' => 'vk-access-' . Str::lower(Str::random(6)),
        ]);

        $this->blogPost = BlogPost::withoutEvents(function () use ($category) {
            return BlogPost::query()->create([
                'blog_category_id' => $category->id,
                'title' => 'Статья VK',
                'slug' => 'vk-post-' . Str::lower(Str::random(8)),
                'content' => '<p>' . str_repeat('Текст ', 25) . '</p>',
                'is_published' => true,
                'published_at' => now()->subHour(),
                'publish_to_vk' => true,
            ]);
        });

        BlogPostSocialPublication::query()->updateOrCreate(
            [
                'blog_post_id' => $this->blogPost->id,
                'platform' => BlogPostSocialPublication::PLATFORM_VK,
            ],
            [
                'status' => BlogPostSocialPublication::STATUS_FAILED,
                'error_message' => 'test error',
            ]
        );
    }

    public function test_vk_retry_route_has_can_blog_view_middleware(): void
    {
        $route = Route::getRoutes()->getByName('admin.blog.posts.vk.retry');
        $this->assertNotNull($route);

        $this->assertContains(
            'can:blog.view',
            $route->gatherMiddleware(),
            'Маршрут admin.blog.posts.vk.retry должен быть защищён can:blog.view'
        );
    }

    public function test_guest_cannot_access_blog_vk_and_admin_pages(): void
    {
        Auth::logout();

        foreach ($this->guestBlockedRoutes() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? []
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_blog_view_gets_403_on_blog_and_vk_routes(): void
    {
        $actor = $this->createUserWithoutPermission('blog.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        foreach ($this->allBlogRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? []
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без blog.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_with_blog_view_gets_200_on_pages_and_success_on_vk_retry(): void
    {
        $this->asAdmin();
        $this->grantBlogViewToCurrentUser();

        foreach ($this->getPageRoutes() as $item) {
            $this->call($item['method'], $item['url'], $item['data'] ?? [])
                ->assertOk()
                ->assertViewIs($item['view']);
        }

        $this->post(route('admin.blog.posts.vk.retry', $this->blogPost))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_admin_posts_index_shows_vk_column_and_retry_for_failed(): void
    {
        $this->asAdmin();
        $this->grantBlogViewToCurrentUser();

        $this->get(route('admin.blog.posts.index'))
            ->assertOk()
            ->assertSee('VK', escape: false)
            ->assertSee('Ошибка', escape: false)
            ->assertSee('Повторить', escape: false);
    }

    public function test_admin_post_edit_shows_vk_form_fields(): void
    {
        $this->asAdmin();
        $this->grantBlogViewToCurrentUser();

        $this->get(route('admin.blog.posts.edit', $this->blogPost))
            ->assertOk()
            ->assertSee('Опубликовать в VK', escape: false)
            ->assertSee('Текст для VK', escape: false);
    }

    public function test_admin_blog_settings_shows_vk_section(): void
    {
        $this->asAdmin();
        $this->grantBlogViewToCurrentUser();

        $this->get(route('admin.blog.settings.edit'))
            ->assertOk()
            ->assertSee('ВКонтакте: публикация статей', escape: false)
            ->assertSee('Текст VK через ИИ', escape: false)
            ->assertSee('Шаблон текста для VK (fallback)', escape: false)
            ->assertSee('Промпт ИИ для анонса VK', escape: false);
    }

    public function test_admin_can_save_blog_settings_with_vk_ai_fields(): void
    {
        $this->asAdmin();
        $this->grantBlogViewToCurrentUser();

        $this->from(route('admin.blog.settings.edit'))
            ->post(route('admin.blog.settings.update'), $this->validBlogSettingsPayload([
                'vk_ai_enabled' => '1',
            ]))
            ->assertRedirect(route('admin.blog.settings.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function guestBlockedRoutes(): array
    {
        return array_merge(
            $this->getPageRoutes(),
            [
                [
                    'method' => 'POST',
                    'url' => route('admin.blog.posts.vk.retry', $this->blogPost),
                ],
                [
                    'method' => 'POST',
                    'url' => route('admin.blog.settings.update'),
                    'data' => $this->validBlogSettingsPayload(),
                ],
            ]
        );
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, view: string}>
     */
    private function getPageRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.blog.posts.index'),
                'view' => 'admin.blog.posts.index',
            ],
            [
                'method' => 'GET',
                'url' => route('admin.blog.posts.create'),
                'view' => 'admin.blog.posts.create',
            ],
            [
                'method' => 'GET',
                'url' => route('admin.blog.posts.edit', $this->blogPost),
                'view' => 'admin.blog.posts.edit',
            ],
            [
                'method' => 'GET',
                'url' => route('admin.blog.settings.edit'),
                'view' => 'admin.blog.settings.edit',
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function allBlogRoutesPayload(): array
    {
        return array_merge(
            $this->getPageRoutes(),
            [
                [
                    'method' => 'POST',
                    'url' => route('admin.blog.posts.vk.retry', $this->blogPost),
                ],
                [
                    'method' => 'POST',
                    'url' => route('admin.blog.settings.update'),
                    'data' => $this->validBlogSettingsPayload(),
                ],
            ]
        );
    }

    private function grantBlogViewToCurrentUser(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => (int) $this->user->role_id,
            'permission_id' => $this->permissionId('blog.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
