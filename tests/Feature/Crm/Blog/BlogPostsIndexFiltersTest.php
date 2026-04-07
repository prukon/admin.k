<?php

namespace Tests\Feature\Crm\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Str;
class BlogPostsIndexFiltersTest extends BlogAdminFeatureTestCase
{
    public function test_index_filters_by_status_category_and_q(): void
    {
        $catA = BlogCategory::query()->create([
            'name' => 'A',
            'slug' => 'a-' . Str::lower(Str::random(8)),
        ]);
        $catB = BlogCategory::query()->create([
            'name' => 'B',
            'slug' => 'b-' . Str::lower(Str::random(8)),
        ]);

        $published = BlogPost::query()->create([
            'blog_category_id' => $catA->id,
            'title' => 'Published AAA',
            'slug' => 'published-aaa-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $draft = BlogPost::query()->create([
            'blog_category_id' => $catA->id,
            'title' => 'Draft BBB',
            'slug' => 'draft-bbb-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
        ]);

        $scheduled = BlogPost::query()->create([
            'blog_category_id' => $catB->id,
            'title' => 'Scheduled CCC',
            'slug' => 'scheduled-ccc-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => true,
            'published_at' => now()->addDay(),
        ]);

        // no filters: see all
        $this->get(route('admin.blog.posts.index'))
            ->assertOk()
            ->assertSee($published->title, escape: false)
            ->assertSee($draft->title, escape: false)
            ->assertSee($scheduled->title, escape: false);

        // published
        $this->get(route('admin.blog.posts.index', ['status' => 'published']))
            ->assertOk()
            ->assertSee($published->title, escape: false)
            ->assertDontSee($draft->title, escape: false)
            ->assertDontSee($scheduled->title, escape: false);

        // draft
        $this->get(route('admin.blog.posts.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee($draft->title, escape: false)
            ->assertDontSee($published->title, escape: false)
            ->assertDontSee($scheduled->title, escape: false);

        // scheduled
        $this->get(route('admin.blog.posts.index', ['status' => 'scheduled']))
            ->assertOk()
            ->assertSee($scheduled->title, escape: false)
            ->assertDontSee($published->title, escape: false)
            ->assertDontSee($draft->title, escape: false);

        // category filter
        $this->get(route('admin.blog.posts.index', ['category' => $catB->id]))
            ->assertOk()
            ->assertSee($scheduled->title, escape: false)
            ->assertDontSee($published->title, escape: false)
            ->assertDontSee($draft->title, escape: false);

        // q by title/slug
        $this->get(route('admin.blog.posts.index', ['q' => 'AAA']))
            ->assertOk()
            ->assertSee($published->title, escape: false)
            ->assertDontSee($draft->title, escape: false)
            ->assertDontSee($scheduled->title, escape: false);

        $this->get(route('admin.blog.posts.index', ['q' => mb_substr($draft->slug, 0, 8)]))
            ->assertOk()
            ->assertSee($draft->title, escape: false)
            ->assertDontSee($published->title, escape: false)
            ->assertDontSee($scheduled->title, escape: false);
    }
}

