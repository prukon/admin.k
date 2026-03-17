<?php

namespace Tests\Feature\Crm\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

class BlogCategoriesCrudTest extends CrmTestCase
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

    public function test_index_and_create_pages_open_with_200(): void
    {
        $this->get(route('admin.blog.categories.index'))
            ->assertOk()
            ->assertViewIs('admin.blog.categories.index')
            ->assertSee('Категории', escape: false);

        $this->get(route('admin.blog.categories.create'))
            ->assertOk()
            ->assertViewIs('admin.blog.categories.create')
            ->assertSee('Новая категория', escape: false);
    }

    public function test_store_validates_slug_and_meta_description_and_creates_category_with_autoslug(): void
    {
        $bad = $this->from(route('admin.blog.categories.create'))
            ->post(route('admin.blog.categories.store'), [
                'name' => '',
                'slug' => 'BAD SLUG',
                'meta_description' => str_repeat('a', 501),
            ]);

        $bad->assertStatus(302);
        $bad->assertSessionHasErrors(['name', 'slug', 'meta_description']);

        $res = $this->post(route('admin.blog.categories.store'), [
            'name' => 'Оплаты и договоры',
            'slug' => '', // should autoslug
            'meta_title' => 'SEO',
            'meta_description' => 'Desc',
        ]);

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success', 'Категория создана.');

        $this->assertDatabaseHas('blog_categories', [
            'name' => 'Оплаты и договоры',
            'meta_title' => 'SEO',
            'meta_description' => 'Desc',
        ]);

        $created = BlogCategory::query()->where('name', 'Оплаты и договоры')->firstOrFail();
        $this->assertNotSame('', (string) $created->slug);
    }

    public function test_store_validates_slug_uniqueness_with_message(): void
    {
        $existing = BlogCategory::query()->create([
            'name' => 'Existing',
            'slug' => 'existing-' . Str::lower(Str::random(8)),
        ]);

        $res = $this->from(route('admin.blog.categories.create'))
            ->post(route('admin.blog.categories.store'), [
                'name' => 'New',
                'slug' => $existing->slug,
            ]);

        $res->assertStatus(302);
        $res->assertSessionHasErrors(['slug']);
    }

    public function test_store_autoslug_for_non_latin_name_is_unique_and_url_safe_when_collision_happens(): void
    {
        BlogCategory::query()->create([
            'name' => 'Existing',
            'slug' => 'category',
        ]);

        $res = $this->post(route('admin.blog.categories.store'), [
            'name' => '!!!', // Str::slug('!!!') === ''
            'slug' => '', // triggers autoslug
        ]);

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();

        $created = BlogCategory::query()->where('name', '!!!')->orderByDesc('id')->firstOrFail();

        $this->assertNotSame('', (string) $created->slug);
        $this->assertNotSame('category', (string) $created->slug);
        $this->assertStringStartsWith('category', (string) $created->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $created->slug);
    }

    public function test_update_page_opens_and_update_autoslug_is_unique_even_for_non_latin_names(): void
    {
        $c1 = BlogCategory::query()->create([
            'name' => '!!!', // Str::slug('!!!') === '' => fallback "category"
            'slug' => 'category',
        ]);
        $c2 = BlogCategory::query()->create([
            'name' => 'Other',
            'slug' => 'category-2',
        ]);

        $this->get(route('admin.blog.categories.edit', $c1))
            ->assertOk()
            ->assertViewIs('admin.blog.categories.edit')
            ->assertSee('Редактирование категории', escape: false);

        $res = $this->put(route('admin.blog.categories.update', $c1), [
            'name' => '!!!',
            'slug' => '', // triggers makeUniqueSlug()
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success', 'Категория обновлена.');

        $c1->refresh();

        $this->assertNotSame('', (string) $c1->slug);
        $this->assertNotSame($c2->slug, $c1->slug);
        $this->assertStringStartsWith('category', (string) $c1->slug);
        $this->assertMatchesRegularExpression('/^category(-\d+)?$/', (string) $c1->slug);
    }

    public function test_destroy_is_blocked_if_category_has_posts_and_returns_error_under_category_key(): void
    {
        $category = BlogCategory::query()->create([
            'name' => 'Категория',
            'slug' => 'cat-' . Str::lower(Str::random(8)),
        ]);

        BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Статья',
            'slug' => 'post-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
        ]);

        $res = $this->from(route('admin.blog.categories.index'))
            ->delete(route('admin.blog.categories.destroy', $category));

        $res->assertStatus(302);
        $res->assertSessionHasErrors(['category']);

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_destroy_deletes_empty_category(): void
    {
        $category = BlogCategory::query()->create([
            'name' => 'Удалить',
            'slug' => 'del-' . Str::lower(Str::random(8)),
        ]);

        $res = $this->delete(route('admin.blog.categories.destroy', $category));

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success', 'Категория удалена.');

        $this->assertDatabaseMissing('blog_categories', [
            'id' => $category->id,
        ]);
    }
}

