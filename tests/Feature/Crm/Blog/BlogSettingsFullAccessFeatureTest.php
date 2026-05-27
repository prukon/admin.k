<?php

namespace Tests\Feature\Crm\Blog;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\Blog\Concerns\ProvidesBlogSettingsPayload;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к странице настроек блога (/admin/blog/settings):
 * с blog.view — GET 200 и успешное сохранение; без права — 403; гость — 302/401.
 */
final class BlogSettingsFullAccessFeatureTest extends CrmTestCase
{
    use ProvidesBlogSettingsPayload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_blog_settings_routes_have_can_blog_view_middleware(): void
    {
        foreach (['admin.blog.settings.edit', 'admin.blog.settings.update'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");
            $this->assertContains(
                'can:blog.view',
                $route->gatherMiddleware(),
                "Маршрут {$routeName} должен быть защищён can:blog.view"
            );
        }
    }

    public function test_guest_cannot_access_blog_settings(): void
    {
        Auth::logout();

        foreach ($this->settingsRoutes() as $item) {
            $response = $this->call($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_blog_view_gets_403_on_settings(): void
    {
        $actor = $this->createUserWithoutPermission('blog.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        foreach ($this->settingsRoutes() as $item) {
            $response = $this->call($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без blog.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_with_blog_view_gets_200_on_settings_edit_page(): void
    {
        $this->asAdmin();
        $this->grantBlogView();

        $this->get(route('admin.blog.settings.edit'))
            ->assertOk()
            ->assertViewIs('admin.blog.settings.edit')
            ->assertSee('SEO-настройки', escape: false)
            ->assertSee('ВКонтакте: публикация статей', escape: false)
            ->assertSee('Текст VK через ИИ', escape: false);
    }

    public function test_admin_with_blog_view_can_save_settings(): void
    {
        $this->asAdmin();
        $this->grantBlogView();

        $this->from(route('admin.blog.settings.edit'))
            ->post(route('admin.blog.settings.update'), $this->validBlogSettingsPayload([
                'index_meta_title' => 'Блог KidsCRM',
                'vk_ai_enabled' => '1',
            ]))
            ->assertRedirect(route('admin.blog.settings.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_admin_settings_edit_returns_200_after_successful_save(): void
    {
        $this->asAdmin();
        $this->grantBlogView();

        $this->from(route('admin.blog.settings.edit'))
            ->post(route('admin.blog.settings.update'), $this->validBlogSettingsPayload())
            ->assertRedirect();

        $this->get(route('admin.blog.settings.edit'))
            ->assertOk()
            ->assertViewIs('admin.blog.settings.edit');
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function settingsRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.blog.settings.edit'),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.blog.settings.update'),
                'data' => $this->validBlogSettingsPayload(),
            ],
        ];
    }

    private function grantBlogView(): void
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
