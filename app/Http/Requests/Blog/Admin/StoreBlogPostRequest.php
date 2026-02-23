<?php

namespace App\Http\Requests\Blog\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('blog-view') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_published' => (int) filter_var($this->input('is_published', false), FILTER_VALIDATE_BOOL),
        ]);

        if ($this->filled('slug')) {
            $this->merge(['slug' => trim((string) $this->input('slug'))]);
        }
    }

    public function rules(): array
    {
        return [
            'blog_category_id' => ['required', 'integer', 'exists:blog_categories,id'],

            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug'),
            ],
            'excerpt' => ['nullable', 'string', 'max:700'],
            'content' => ['required', 'string', 'min:50'],

            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:500'],

            'is_published' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date', 'required_if:is_published,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'blog_category_id' => 'Категория',
            'title' => 'Заголовок',
            'slug' => 'Slug (часть URL)',
            'excerpt' => 'Краткое описание',
            'content' => 'Текст статьи',
            'cover_image' => 'Обложка',
            'meta_title' => 'SEO Title',
            'meta_description' => 'SEO Description',
            'canonical_url' => 'Canonical URL',
            'is_published' => 'Публикация',
            'published_at' => 'Дата публикации',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только латиницу в нижнем регистре, цифры и дефисы (например, "kak-vybrat-crm").',
            'slug.unique' => 'Статья с таким slug уже существует.',
            'published_at.required_if' => 'Укажите дату публикации для опубликованной статьи.',
            'cover_image.image' => 'Файл обложки должен быть изображением.',
            'cover_image.mimes' => 'Обложка должна быть в формате JPG, PNG или WEBP.',
            'cover_image.max' => 'Размер обложки не должен превышать 4 МБ.',
        ];
    }
}

