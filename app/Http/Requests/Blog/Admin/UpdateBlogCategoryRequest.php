<?php

namespace App\Http\Requests\Blog\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBlogCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('blog-view') === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge(['slug' => trim((string) $this->input('slug'))]);
        }
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = is_object($category) && method_exists($category, 'getKey')
            ? (int) $category->getKey()
            : (int) $category;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_categories', 'slug')->ignore($categoryId),
            ],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Название',
            'slug' => 'Slug (часть URL)',
            'meta_title' => 'SEO Title',
            'meta_description' => 'SEO Description',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только латиницу в нижнем регистре, цифры и дефисы (например, "oplata-i-dogovory").',
            'slug.unique' => 'Категория с таким slug уже существует.',
            'meta_description.max' => 'SEO Description не должен превышать :max символов.',
        ];
    }
}

