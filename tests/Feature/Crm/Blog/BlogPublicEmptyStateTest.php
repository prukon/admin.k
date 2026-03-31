<?php

namespace Tests\Feature\Crm\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Str;
use Tests\TestCase;

class BlogPublicEmptyStateTest extends TestCase
{
    public function test_public_blog_index_is_200_even_when_there_are_no_posts(): void
    {
        $this->get(route('blog.index'))
            ->assertOk()
            ->assertViewIs('blog.index')
            ->assertSee('Блог', escape: false);
    }

    public function test_public_blog_category_page_is_200_even_when_there_are_no_published_posts_in_it(): void
    {
        $category = BlogCategory::query()->create([
            'name' => 'Категория',
            'slug' => 'cat-' . Str::lower(Str::random(8)),
        ]);

        // Draft post should not appear publicly
        BlogPost::query()->create([
            'blog_category_id' => $category->id,
            'title' => 'Черновик',
            'slug' => 'draft-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст ', 20) . '</p>',
            'is_published' => false,
            'published_at' => now(),
        ]);

        $this->get(route('blog.category', $category->slug))
            ->assertOk()
            ->assertViewIs('blog.category')
            ->assertSee($category->name, escape: false);
    }
}

