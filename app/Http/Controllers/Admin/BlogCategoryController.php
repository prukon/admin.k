<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\Admin\StoreBlogCategoryRequest;
use App\Http\Requests\Blog\Admin\UpdateBlogCategoryRequest;
use App\Models\BlogCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.blog.categories.index', [
            'categories' => BlogCategory::query()
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.categories.create');
    }

    public function store(StoreBlogCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = $this->makeUniqueSlug($data['name']);
        }

        BlogCategory::create($data);

        return redirect()
            ->route('admin.blog.categories.index')
            ->with('success', 'Категория создана.');
    }

    public function edit(BlogCategory $category): View
    {
        return view('admin.blog.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateBlogCategoryRequest $request, BlogCategory $category): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = $this->makeUniqueSlug($data['name'], $category->id);
        }

        $category->update($data);

        return redirect()
            ->route('admin.blog.categories.index')
            ->with('success', 'Категория обновлена.');
    }

    public function destroy(BlogCategory $category): RedirectResponse
    {
        if ($category->posts()->exists()) {
            return back()->withErrors([
                'category' => 'Нельзя удалить категорию, в которой есть статьи.',
            ]);
        }

        $category->delete();

        return redirect()
            ->route('admin.blog.categories.index')
            ->with('success', 'Категория удалена.');
    }

    private function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $fallback = $base !== '' ? $base : 'category';
        $slug = $fallback;

        $i = 1;
        while (BlogCategory::query()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()
        ) {
            $i++;
            $slug = $fallback . '-' . $i;
        }

        return $slug;
    }
}

