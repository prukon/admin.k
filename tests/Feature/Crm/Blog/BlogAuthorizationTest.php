<?php

namespace Tests\Feature\Crm\Blog;

use Illuminate\Support\Facades\Route;
use Tests\Feature\Crm\CrmTestCase;

class BlogAuthorizationTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_blog_admin_routes_have_can_blog_view_middleware(): void
    {
        $routeNames = [
            'admin.blog.categories.index',
            'admin.blog.categories.create',
            'admin.blog.categories.store',
            'admin.blog.categories.edit',
            'admin.blog.categories.update',
            'admin.blog.categories.destroy',

            'admin.blog.posts.index',
            'admin.blog.posts.create',
            'admin.blog.posts.store',
            'admin.blog.posts.edit',
            'admin.blog.posts.update',
            'admin.blog.posts.destroy',

            'admin.blog.posts.ai.start',
            'admin.blog.posts.ai.post.start',
            'admin.blog.posts.ai.status',
            'admin.blog.posts.ai.images.regenerate',

            'admin.blog.settings.edit',
            'admin.blog.settings.update',
        ];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Маршрут {$routeName} не найден");

            $middlewares = $route->gatherMiddleware();

            $this->assertTrue(
                in_array('can:blog-view', $middlewares, true),
                "Маршрут {$routeName} должен быть защищён middleware can:blog-view"
            );
        }
    }

    public function test_guest_cannot_access_blog_admin_routes(): void
    {
        auth()->logout();

        $this->get(route('admin.blog.posts.index'))->assertStatus(302);
        $this->get(route('admin.blog.categories.index'))->assertStatus(302);
        $this->get(route('admin.blog.settings.edit'))->assertStatus(302);

        // JSON routes for guest usually return 401 (Sanctum/session guard)
        $this->postJson(route('admin.blog.posts.ai.start'), [])->assertStatus(401);
    }

    public function test_user_without_blog_view_permission_gets_403_for_blog_admin_routes(): void
    {
        $actor = $this->createUserWithoutPermission('blog.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.blog.posts.index'))->assertStatus(403);
        $this->get(route('admin.blog.categories.index'))->assertStatus(403);
        $this->get(route('admin.blog.settings.edit'))->assertStatus(403);

        $this->postJson(route('admin.blog.posts.ai.start'), [])->assertStatus(403);
    }
}

