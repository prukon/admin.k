<?php

namespace App\Http\Requests\Blog\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class StartAiBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('blog-view') === true;
    }

    protected function prepareForValidation(): void
    {
        $defaultCover = (int) (Setting::query()
            ->where('name', 'blog.ai.images.default_cover_count')
            ->whereNull('partner_id')
            ->value('text') ?? 1);
        $defaultInline = (int) (Setting::query()
            ->where('name', 'blog.ai.images.default_inline_count')
            ->whereNull('partner_id')
            ->value('text') ?? 2);
        $defaultInline = max(0, min(3, $defaultInline));

        $this->merge([
            'prompt' => is_string($this->input('prompt')) ? trim((string) $this->input('prompt')) : $this->input('prompt'),
            'want_cover_image' => filter_var($this->input('want_cover_image', $defaultCover >= 1), FILTER_VALIDATE_BOOL),
            'inline_images_count' => is_numeric($this->input('inline_images_count', $defaultInline))
                ? (int) $this->input('inline_images_count', $defaultInline)
                : $this->input('inline_images_count', $defaultInline),
        ]);
    }

    public function rules(): array
    {
        return [
            'blog_category_id' => ['required', 'integer', 'exists:blog_categories,id'],
            'prompt' => ['required', 'string', 'min:20', 'max:4000'],
            'want_cover_image' => ['required', 'boolean'],
            'inline_images_count' => ['required', 'integer', 'min:0', 'max:3'],
        ];
    }

    public function attributes(): array
    {
        return [
            'blog_category_id' => 'Категория',
            'prompt' => 'Промпт',
            'want_cover_image' => 'Обложка',
            'inline_images_count' => 'Изображения внутри',
        ];
    }

    public function messages(): array
    {
        return [
            'prompt.required' => 'Введите промпт для статьи.',
            'prompt.min' => 'Промпт слишком короткий — добавьте деталей (минимум :min символов).',
            'prompt.max' => 'Промпт слишком длинный (максимум :max символов).',
            'blog_category_id.required' => 'Выберите категорию.',
            'blog_category_id.exists' => 'Выберите корректную категорию.',
            'inline_images_count.max' => 'Можно добавить не более :max изображений внутри статьи.',
        ];
    }
}

