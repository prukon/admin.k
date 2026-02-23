<?php

namespace Tests\Feature\Crm\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

class BlogPostsCrudTest extends CrmTestCase
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

    private function category(): BlogCategory
    {
        return BlogCategory::query()->create([
            'name' => 'Категория',
            'slug' => 'cat-' . Str::lower(Str::random(8)),
        ]);
    }

    private function useTmpPublicDisk(): string
    {
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_public_disk_'
            . Str::uuid()->toString();

        if (!is_dir($root)) {
            @mkdir($root, 0777, true);
        }
        @chmod($root, 0777);

        config([
            'filesystems.disks.public.driver' => 'local',
            'filesystems.disks.public.root' => $root,
        ]);

        app('filesystem')->forgetDisk('public');

        return $root;
    }

    public function test_index_create_edit_pages_open_with_200(): void
    {
        $category = $this->category();

        $post = BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Статья',
            'slug' => 'post-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
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
    }

    public function test_store_validates_required_fields_and_published_at_required_if_published(): void
    {
        $category = $this->category();

        $bad = $this->from(route('admin.blog.posts.create'))
            ->post(route('admin.blog.posts.store'), [
                'blog_category_id' => $category->id,
                'title' => '',
                'slug' => 'BAD SLUG',
                'excerpt' => str_repeat('a', 701),
                'content' => 'short',
                'canonical_url' => 'notaurl',
                'is_published' => true,
                'published_at' => null,
            ]);

        $bad->assertStatus(302);
        $bad->assertSessionHasErrors([
            'title',
            'slug',
            'excerpt',
            'content',
            'canonical_url',
            'published_at',
        ]);
    }

    public function test_store_creates_draft_sets_published_at_and_purifies_content_and_excerpt(): void
    {
        $category = $this->category();

        $res = $this->post(route('admin.blog.posts.store'), [
            'blog_category_id' => $category->id,
            'title' => 'Новая статья',
            'slug' => '',
            'excerpt' => '<p>Ok</p><script>alert(1)</script>' . str_repeat('a', 50),
            'content' => '<p>Ok</p><script>alert(1)</script>' . str_repeat('a', 80),
            'meta_title' => 'SEO',
            'meta_description' => 'Desc',
            'canonical_url' => 'https://example.test',
            'is_published' => false,
            'published_at' => null,
        ]);

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success', 'Статья создана.');

        $post = BlogPost::query()->where('title', 'Новая статья')->firstOrFail();

        $this->assertNotSame('', (string) $post->slug);
        $this->assertNotNull($post->published_at, 'Draft post should have published_at auto-filled');

        $this->assertStringNotContainsString('<script', (string) $post->content);
        $this->assertStringNotContainsString('<script', (string) ($post->excerpt ?? ''));
    }

    public function test_store_cover_image_is_saved_to_public_disk(): void
    {
        $this->useTmpPublicDisk();

        $category = $this->category();

        $file = UploadedFile::fake()->image('cover.webp', 1400, 900)->size(500);

        $res = $this->post(route('admin.blog.posts.store'), [
            'blog_category_id' => $category->id,
            'title' => 'С картинкой',
            'slug' => '',
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => null,
            'cover_image' => $file,
        ]);

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();

        $post = BlogPost::query()->where('title', 'С картинкой')->firstOrFail();
        $this->assertNotNull($post->cover_image_path);

        $this->assertTrue(Storage::disk('public')->exists($post->cover_image_path));
        $this->assertStringStartsWith('blog/covers/', (string) $post->cover_image_path);
    }

    public function test_update_replaces_cover_image_and_deletes_old_file(): void
    {
        $this->useTmpPublicDisk();

        $category = $this->category();

        $res = $this->post(route('admin.blog.posts.store'), [
            'blog_category_id' => $category->id,
            'title' => 'Обложка',
            'slug' => '',
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => null,
            'cover_image' => UploadedFile::fake()->image('cover1.png', 1200, 630),
        ]);
        $res->assertStatus(302)->assertSessionHasNoErrors();

        $post = BlogPost::query()->where('title', 'Обложка')->firstOrFail();
        $old = (string) $post->cover_image_path;
        $this->assertNotSame('', $old);
        $this->assertTrue(Storage::disk('public')->exists($old));

        $res2 = $this->put(route('admin.blog.posts.update', $post), [
            'blog_category_id' => $category->id,
            'title' => 'Обложка 2',
            'slug' => $post->slug,
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => null,
            'cover_image' => UploadedFile::fake()->image('cover2.webp', 1200, 630),
        ]);

        $res2->assertStatus(302);
        $res2->assertSessionHasNoErrors();
        $res2->assertSessionHas('success', 'Статья обновлена.');

        $post->refresh();

        $new = (string) $post->cover_image_path;
        $this->assertNotSame($old, $new);
        $this->assertFalse(Storage::disk('public')->exists($old));
        $this->assertTrue(Storage::disk('public')->exists($new));
    }

    public function test_update_draft_does_not_overwrite_existing_published_at_when_missing_in_request(): void
    {
        $category = $this->category();

        $post = BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Черновик',
            'slug' => 'draft-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now()->subDays(2),
        ]);

        $before = $post->published_at?->toDateTimeString();

        $res = $this->put(route('admin.blog.posts.update', $post), [
            'blog_category_id' => $category->id,
            'title' => 'Черновик 2',
            'slug' => $post->slug,
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            // published_at omitted intentionally
        ]);

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame($before, $post->published_at?->toDateTimeString());
    }

    public function test_store_autoslug_for_non_latin_title_is_unique_and_url_safe_when_collision_happens(): void
    {
        $category = $this->category();

        BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Existing',
            'slug' => 'post',
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
        ]);

        $res = $this->post(route('admin.blog.posts.store'), [
            'blog_category_id' => $category->id,
            'title' => '!!!', // Str::slug('!!!') === ''
            'slug' => '',
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
        ]);

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();

        $created = BlogPost::query()->where('title', '!!!')->orderByDesc('id')->firstOrFail();

        $this->assertNotSame('', (string) $created->slug);
        $this->assertNotSame('post', (string) $created->slug);
        $this->assertStringStartsWith('post', (string) $created->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $created->slug);
    }

    public function test_destroy_soft_deletes_post(): void
    {
        $category = $this->category();

        $post = BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Удалить',
            'slug' => 'del-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
        ]);

        $res = $this->delete(route('admin.blog.posts.destroy', $post));

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success', 'Статья удалена.');

        $this->assertSoftDeleted('blog_posts', [
            'id' => $post->id,
        ]);
    }
}

