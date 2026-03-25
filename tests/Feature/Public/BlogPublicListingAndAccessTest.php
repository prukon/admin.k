<?php

namespace Tests\Feature\Public;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Публичный блог: доступ без авторизации (200) и разметка карточек списка (partial _post_card).
 */
class BlogPublicListingAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Как в CrmTestCase: не зависеть от прав на storage/framework/views в CI/контейнерах.
        $compiled = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_compiled_views_blog_public_'
            . (string) Str::uuid();

        if (! is_dir($compiled)) {
            @mkdir($compiled, 0777, true);
        }
        @chmod($compiled, 0777);
        config(['view.compiled' => $compiled]);
    }

    private function makeCategory(string $suffix = ''): BlogCategory
    {
        $rand = Str::lower(Str::random(8));

        return BlogCategory::query()->create([
            'name' => 'Категория ' . $suffix . $rand,
            'slug' => 'cat-' . $suffix . $rand,
            'meta_title' => null,
            'meta_description' => null,
        ]);
    }

    private function makePublishedPost(
        BlogCategory $category,
        array $overrides = []
    ): BlogPost {
        $rand = Str::lower(Str::random(8));

        return BlogPost::query()->create(array_merge([
            'blog_category_id' => $category->id,
            'title' => 'Заголовок ' . $rand,
            'slug' => 'post-' . $rand,
            'excerpt' => '<p>Уникальный отрывок ' . $rand . '</p>',
            'content' => '<p>' . str_repeat('Текст ', 30) . '</p>',
            'cover_image_path' => null,
            'is_published' => true,
            'published_at' => now()->subHours(2),
        ], $overrides));
    }

    public function test_public_blog_routes_are_registered_without_auth_middleware(): void
    {
        foreach (['blog.index', 'blog.category', 'blog.show'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Маршрут {$name} должен существовать");

            $middleware = $route->gatherMiddleware();
            $this->assertNotContains('auth', $middleware, "Маршрут {$name} не должен требовать middleware auth");
            $authPrefixed = collect($middleware)->filter(
                static fn ($m) => is_string($m) && str_starts_with($m, 'auth:')
            );
            $this->assertTrue(
                $authPrefixed->isEmpty(),
                "Маршрут {$name} не должен использовать префикс auth: в middleware"
            );
        }
    }

    public function test_guest_gets_200_on_blog_index_category_show_and_pagination(): void
    {
        $category = $this->makeCategory('a');

        $posts = [];
        for ($i = 0; $i < 13; $i++) {
            $posts[] = $this->makePublishedPost($category, [
                'title' => 'Статья номер ' . $i,
                'slug' => 'article-' . $i . '-' . Str::lower(Str::random(6)),
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $newest = $posts[0];
        $onSecondPage = $posts[12];

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertViewIs('blog.index');

        $this->get(route('blog.index', ['page' => 2]))
            ->assertOk()
            ->assertViewIs('blog.index')
            ->assertSee($onSecondPage->title, false);

        $this->get(route('blog.index', ['page' => 1]))
            ->assertOk()
            ->assertSee($newest->title, false);

        $this->get(route('blog.category', $category->slug))
            ->assertOk()
            ->assertViewIs('blog.category');

        $this->get(route('blog.category', $category->slug, ['page' => 2]))
            ->assertOk()
            ->assertViewIs('blog.category');

        $this->get(route('blog.show', $newest->slug))
            ->assertOk()
            ->assertViewIs('blog.show')
            ->assertSee($newest->title, false);
    }

    public function test_blog_index_is_200_when_there_are_no_posts(): void
    {
        $this->get(route('blog.index'))
            ->assertOk()
            ->assertViewIs('blog.index');
    }

    public function test_listing_markup_includes_mobile_overlay_category_date_and_media_link_aria(): void
    {
        $category = $this->makeCategory();
        $post = $this->makePublishedPost($category, [
            'title' => 'Особый заголовок для оверлея',
        ]);

        $html = $this->get(route('blog.index'))->assertOk()->getContent();

        $this->assertStringContainsString('blog-card__figure', $html);
        $this->assertStringContainsString('blog-card__overlay--mobile', $html);
        $this->assertStringContainsString('blog-card__overlay-chip', $html);
        $this->assertStringContainsString($category->name, $html);
        $this->assertStringContainsString('blog-card__overlay-title', $html);
        $this->assertStringContainsString('Особый заголовок для оверлея', $html);
        $this->assertStringContainsString('aria-label="Особый заголовок для оверлея"', $html);
        $this->assertStringContainsString('datetime="' . $post->published_at->toAtomString() . '"', $html);
        $this->assertStringContainsString($post->published_at->format('d.m.Y'), $html);
    }

    public function test_index_includes_desktop_category_badge_links_category_listing_does_not(): void
    {
        $category = $this->makeCategory();
        $this->makePublishedPost($category);

        $indexHtml = $this->get(route('blog.index'))->assertOk()->getContent();
        $this->assertGreaterThanOrEqual(
            1,
            substr_count($indexHtml, 'blog-badge text-decoration-none'),
            'На главной списка блога в карточках должна быть ссылка-категория (badge) для md+'
        );

        $categoryHtml = $this->get(route('blog.category', $category->slug))->assertOk()->getContent();
        $this->assertSame(
            0,
            substr_count($categoryHtml, 'blog-badge text-decoration-none'),
            'На странице категории в карточках постов не показываем badge категории (как в вёрстке desktopCategoryBadge=false)'
        );
    }

    public function test_first_card_image_is_eager_following_cards_use_lazy_loading(): void
    {
        $category = $this->makeCategory();
        $this->makePublishedPost($category, ['slug' => 'p-a-' . Str::lower(Str::random(6))]);
        $this->makePublishedPost($category, ['slug' => 'p-b-' . Str::lower(Str::random(6))]);

        $html = $this->get(route('blog.index'))->assertOk()->getContent();

        preg_match_all('/<img\b[^>]*\bclass="[^"]*\bblog-card__img\b[^"]*"[^>]*>/', $html, $matches);
        $this->assertCount(2, $matches[0], 'Ожидаем ровно два изображения карточек на странице');

        $this->assertStringNotContainsString('loading="lazy"', $matches[0][0]);
        $this->assertStringContainsString('loading="lazy"', $matches[0][1]);
    }

    public function test_cover_image_uses_storage_path_in_img_src(): void
    {
        $category = $this->makeCategory();
        $this->makePublishedPost($category, [
            'cover_image_path' => 'blog/covers/test-cover.jpg',
            'slug' => 'with-cover-' . Str::lower(Str::random(6)),
        ]);

        $html = $this->get(route('blog.index'))->assertOk()->getContent();

        $this->assertStringContainsString('storage/blog/covers/test-cover.jpg', $html);
    }
}
