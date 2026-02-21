<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\Admin\StoreBlogPostRequest;
use App\Http\Requests\Blog\Admin\UpdateBlogPostRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Mews\Purifier\Facades\Purifier;

class BlogPostController extends Controller
{
    public function index(): View
    {
        $query = BlogPost::query()->with('category');

        $status = request('status', 'all');
        if ($status === 'published') {
            $query->published();
        } elseif ($status === 'draft') {
            $query->where('is_published', false);
        } elseif ($status === 'scheduled') {
            $query->where('is_published', true)->whereNotNull('published_at')->where('published_at', '>', now());
        }

        if ($categoryId = request('category')) {
            $query->where('blog_category_id', (int) $categoryId);
        }

        if ($q = trim((string) request('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', '%' . $q . '%')
                    ->orWhere('slug', 'like', '%' . $q . '%');
            });
        }

        $posts = $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $publishedCount = BlogPost::query()->published()->count();
        $draftCount = BlogPost::query()->where('is_published', false)->count();
        $scheduledCount = BlogPost::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '>', now())
            ->count();
        $lastPublishedAt = BlogPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->value('published_at');

        return view('admin.blog.posts.index', [
            'posts' => $posts,
            'categories' => BlogCategory::query()->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'published' => $publishedCount,
                'drafts' => $draftCount,
                'scheduled' => $scheduledCount,
                'last_published_at' => $lastPublishedAt ? $lastPublishedAt->format('d.m.Y H:i') : '—',
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.posts.create', [
            'categories' => BlogCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreBlogPostRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['content'] = Purifier::clean($data['content']);
        if (!empty($data['excerpt'])) {
            $data['excerpt'] = Purifier::clean($data['excerpt']);
        }

        if (empty($data['slug'])) {
            $data['slug'] = $this->makeUniqueSlug($data['title']);
        }

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')->store('blog/covers', 'public');
        }

        BlogPost::create($data);

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', 'Статья создана.');
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.blog.posts.edit', [
            'post' => $post,
            'categories' => BlogCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $data = $request->validated();

        $data['content'] = Purifier::clean($data['content']);
        if (!empty($data['excerpt'])) {
            $data['excerpt'] = Purifier::clean($data['excerpt']);
        }

        if (empty($data['slug'])) {
            $data['slug'] = $this->makeUniqueSlug($data['title'], $post->id);
        }

        if ($request->hasFile('cover_image')) {
            if (!empty($post->cover_image_path)) {
                Storage::disk('public')->delete($post->cover_image_path);
            }
            $data['cover_image_path'] = $request->file('cover_image')->store('blog/covers', 'public');
        }

        $post->update($data);

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', 'Статья обновлена.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $post->delete();

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', 'Статья удалена.');
    }

    private function makeUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : 'post';

        $i = 1;
        while (BlogPost::query()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()
        ) {
            $i++;
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}

