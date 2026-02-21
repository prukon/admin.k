<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('blog.index', [
            'posts' => BlogPost::query()
                ->published()
                ->with('category')
                ->orderByDesc('published_at')
                ->paginate(12),
        ]);
    }

    public function show(string $slug): View
    {
        $post = BlogPost::query()
            ->published()
            ->with('category')
            ->where('slug', $slug)
            ->firstOrFail();

        return view('blog.show', [
            'post' => $post,
        ]);
    }

    public function category(string $slug): View
    {
        $category = BlogCategory::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('blog.category', [
            'category' => $category,
            'posts' => BlogPost::query()
                ->published()
                ->where('blog_category_id', $category->id)
                ->orderByDesc('published_at')
                ->paginate(12),
        ]);
    }
}

