<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BlogSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.blog.settings.edit', [
            'settings' => [
                'index_meta_title' => $this->getText('blog.index.meta_title'),
                'index_meta_description' => $this->getText('blog.index.meta_description'),
                'default_og_image_path' => $this->getText('blog.default.og_image_path'),
                'post_title_template' => $this->getText('blog.post.title_template', '{title} — Блог | kidscrm.online'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'index_meta_title' => ['nullable', 'string', 'max:255'],
                'index_meta_description' => ['nullable', 'string', 'max:500'],
                'post_title_template' => ['nullable', 'string', 'max:255'],
                'default_og_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ], [
                'default_og_image.image' => 'Файл должен быть изображением.',
                'default_og_image.mimes' => 'Изображение должно быть в формате JPG, PNG или WEBP.',
                'default_og_image.max' => 'Размер изображения не должен превышать 4 МБ.',
                'index_meta_description.max' => 'SEO Description не должен превышать :max символов.',
            ], [
                'index_meta_title' => 'SEO Title (страница блога)',
                'index_meta_description' => 'SEO Description (страница блога)',
                'post_title_template' => 'Шаблон SEO Title (статья)',
                'default_og_image' => 'OG‑изображение по умолчанию',
            ]);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        }

        $this->setText('blog.index.meta_title', $validated['index_meta_title'] ?? null);
        $this->setText('blog.index.meta_description', $validated['index_meta_description'] ?? null);
        $this->setText('blog.post.title_template', $validated['post_title_template'] ?? '{title} — Блог | kidscrm.online');

        if ($request->hasFile('default_og_image')) {
            $old = $this->getText('blog.default.og_image_path');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('default_og_image')->store('blog/seo', 'public');
            $this->setText('blog.default.og_image_path', $path);
        }

        return redirect()
            ->route('admin.blog.settings.edit')
            ->with('success', 'Настройки блога сохранены.');
    }

    private function getText(string $name, ?string $default = null): ?string
    {
        $row = Setting::query()
            ->where('name', $name)
            ->whereNull('partner_id')
            ->first(['text']);

        $text = $row?->text;
        return ($text === null || $text === '') ? $default : $text;
    }

    private function setText(string $name, ?string $value): void
    {
        Setting::query()->updateOrCreate(
            ['name' => $name, 'partner_id' => null],
            ['text' => $value]
        );
    }
}

