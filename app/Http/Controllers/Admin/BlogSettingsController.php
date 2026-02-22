<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\BlogAi\DefaultPrompts;
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

                'ai_model' => $this->getText('blog.ai.model', 'gpt-5.1'),
                'ai_daily_budget_usd' => $this->getText('blog.ai.daily_budget_usd', '5'),
                'ai_price_input_per_1m' => $this->getText('blog.ai.price_input_per_1m', ''),
                'ai_price_output_per_1m' => $this->getText('blog.ai.price_output_per_1m', ''),
                'ai_max_output_tokens' => $this->getText('blog.ai.max_output_tokens', '4500'),
                'ai_prompt_template' => $this->getText('blog.ai.prompt_template', DefaultPrompts::articleTemplate()),

                // AI images (blog)
                'ai_images_enabled' => $this->getText('blog.ai.images.enabled', '1'),
                'ai_images_model' => $this->getText('blog.ai.images.model', 'gpt-image-1'),
                'ai_images_quality' => $this->getText('blog.ai.images.quality', 'medium'),
                'ai_images_background' => $this->getText('blog.ai.images.background', 'opaque'),
                'ai_images_output_format' => $this->getText('blog.ai.images.output_format', 'webp'),
                'ai_images_output_compression' => $this->getText('blog.ai.images.output_compression', '85'),

                'ai_images_default_cover_count' => $this->getText('blog.ai.images.default_cover_count', '1'),
                'ai_images_default_inline_count' => $this->getText('blog.ai.images.default_inline_count', '2'),

                'ai_images_cover_target_size' => $this->getText('blog.ai.images.cover_target_size', '1200x630'),
                'ai_images_inline_target_size_43' => $this->getText('blog.ai.images.inline_target_size_43', '960x720'),
                'ai_images_inline_target_size_square' => $this->getText('blog.ai.images.inline_target_size_square', '768x768'),

                'ai_images_cost_cover_usd' => $this->getText('blog.ai.images.cost_cover_usd', '0.02'),
                'ai_images_cost_inline_usd' => $this->getText('blog.ai.images.cost_inline_usd', '0.01'),

                'ai_images_style' => $this->getText('blog.ai.images.style', 'Плоская современная иллюстрация, чистые формы, мягкие тени, дружелюбный стиль, без текста.'),
                'ai_images_palette' => $this->getText('blog.ai.images.palette', '#1F6FEB,#F97316,#111827,#FFFFFF'),
                'ai_images_rules' => $this->getText('blog.ai.images.rules', "Запрещено: любой текст, буквы, цифры, логотипы, водяные знаки, надписи на вывесках.\nРазрешено: персонажи/люди в виде иллюстраций.\nСтиль: единый, аккуратный, без агрессии."),
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

                'ai_model' => ['nullable', 'string', 'max:255'],
                'ai_daily_budget_usd' => ['nullable', 'numeric', 'min:0', 'max:1000'],
                'ai_price_input_per_1m' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                'ai_price_output_per_1m' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                'ai_max_output_tokens' => ['nullable', 'integer', 'min:100', 'max:8000'],
                'ai_prompt_template' => ['nullable', 'string', 'min:50', 'max:20000'],

                'ai_images_enabled' => ['nullable', 'in:0,1'],
                'ai_images_model' => ['nullable', 'string', 'max:255'],
                'ai_images_quality' => ['nullable', 'in:auto,low,medium,high'],
                'ai_images_background' => ['nullable', 'in:auto,transparent,opaque'],
                'ai_images_output_format' => ['nullable', 'in:webp,png,jpeg'],
                'ai_images_output_compression' => ['nullable', 'integer', 'min:0', 'max:100'],

                'ai_images_default_cover_count' => ['nullable', 'integer', 'min:0', 'max:1'],
                'ai_images_default_inline_count' => ['nullable', 'integer', 'min:0', 'max:3'],

                'ai_images_cover_target_size' => ['nullable', 'regex:/^\d{2,4}x\d{2,4}$/'],
                'ai_images_inline_target_size_43' => ['nullable', 'regex:/^\d{2,4}x\d{2,4}$/'],
                'ai_images_inline_target_size_square' => ['nullable', 'regex:/^\d{2,4}x\d{2,4}$/'],

                'ai_images_cost_cover_usd' => ['nullable', 'numeric', 'min:0', 'max:1000'],
                'ai_images_cost_inline_usd' => ['nullable', 'numeric', 'min:0', 'max:1000'],

                'ai_images_style' => ['nullable', 'string', 'min:10', 'max:2000'],
                'ai_images_palette' => ['nullable', 'string', 'max:200'],
                'ai_images_rules' => ['nullable', 'string', 'max:4000'],
            ], [
                'default_og_image.image' => 'Файл должен быть изображением.',
                'default_og_image.mimes' => 'Изображение должно быть в формате JPG, PNG или WEBP.',
                'default_og_image.max' => 'Размер изображения не должен превышать 4 МБ.',
                'index_meta_description.max' => 'SEO Description не должен превышать :max символов.',

                'ai_daily_budget_usd.numeric' => 'Бюджет должен быть числом (например, 5).',
                'ai_daily_budget_usd.min' => 'Бюджет не может быть отрицательным.',
                'ai_price_input_per_1m.numeric' => 'Цена input-токенов должна быть числом.',
                'ai_price_output_per_1m.numeric' => 'Цена output-токенов должна быть числом.',
                'ai_max_output_tokens.integer' => 'Max output tokens должен быть целым числом.',
                'ai_prompt_template.min' => 'Шаблон промпта слишком короткий.',
                'ai_prompt_template.max' => 'Шаблон промпта слишком длинный.',

                'ai_images_output_compression.integer' => 'Сжатие WEBP должно быть целым числом.',
                'ai_images_output_compression.min' => 'Сжатие WEBP не может быть меньше :min.',
                'ai_images_output_compression.max' => 'Сжатие WEBP не может быть больше :max.',
                'ai_images_cover_target_size.regex' => 'Размер обложки должен быть в формате WIDTHxHEIGHT (например, 1200x630).',
                'ai_images_inline_target_size_43.regex' => 'Размер inline (4:3) должен быть в формате WIDTHxHEIGHT.',
                'ai_images_inline_target_size_square.regex' => 'Размер inline (1:1) должен быть в формате WIDTHxHEIGHT.',
            ], [
                'index_meta_title' => 'SEO Title (страница блога)',
                'index_meta_description' => 'SEO Description (страница блога)',
                'post_title_template' => 'Шаблон SEO Title (статья)',
                'default_og_image' => 'OG‑изображение по умолчанию',

                'ai_model' => 'Модель OpenAI',
                'ai_daily_budget_usd' => 'Бюджет в день ($)',
                'ai_price_input_per_1m' => 'Цена input-токенов ($ за 1M)',
                'ai_price_output_per_1m' => 'Цена output-токенов ($ за 1M)',
                'ai_max_output_tokens' => 'Max output tokens',
                'ai_prompt_template' => 'Шаблон промпта для статьи',

                'ai_images_enabled' => 'Изображения: включено',
                'ai_images_model' => 'Модель изображений',
                'ai_images_quality' => 'Качество изображений',
                'ai_images_background' => 'Фон изображений',
                'ai_images_output_format' => 'Формат изображений',
                'ai_images_output_compression' => 'Сжатие WEBP (%)',
                'ai_images_default_cover_count' => 'Дефолт: обложки (шт.)',
                'ai_images_default_inline_count' => 'Дефолт: inline (шт.)',
                'ai_images_cover_target_size' => 'Обложка: целевой размер',
                'ai_images_inline_target_size_43' => 'Inline (4:3): целевой размер',
                'ai_images_inline_target_size_square' => 'Inline (1:1): целевой размер',
                'ai_images_cost_cover_usd' => 'Стоимость обложки ($)',
                'ai_images_cost_inline_usd' => 'Стоимость inline-изображения ($)',
                'ai_images_style' => 'Стиль изображений',
                'ai_images_palette' => 'Палитра (HEX)',
                'ai_images_rules' => 'Правила/запреты для изображений',
            ]);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        }

        $this->setText('blog.index.meta_title', $validated['index_meta_title'] ?? null);
        $this->setText('blog.index.meta_description', $validated['index_meta_description'] ?? null);
        $this->setText('blog.post.title_template', $validated['post_title_template'] ?? '{title} — Блог | kidscrm.online');

        $this->setText('blog.ai.model', $validated['ai_model'] ?? 'gpt-5.1');
        $this->setText('blog.ai.daily_budget_usd', isset($validated['ai_daily_budget_usd']) ? (string) $validated['ai_daily_budget_usd'] : '5');
        $this->setText('blog.ai.price_input_per_1m', isset($validated['ai_price_input_per_1m']) ? (string) $validated['ai_price_input_per_1m'] : null);
        $this->setText('blog.ai.price_output_per_1m', isset($validated['ai_price_output_per_1m']) ? (string) $validated['ai_price_output_per_1m'] : null);
        $this->setText('blog.ai.max_output_tokens', isset($validated['ai_max_output_tokens']) ? (string) $validated['ai_max_output_tokens'] : '4500');
        $this->setText('blog.ai.prompt_template', $validated['ai_prompt_template'] ?? DefaultPrompts::articleTemplate());

        $this->setText('blog.ai.images.enabled', isset($validated['ai_images_enabled']) ? (string) $validated['ai_images_enabled'] : '1');
        $this->setText('blog.ai.images.model', $validated['ai_images_model'] ?? 'gpt-image-1');
        $this->setText('blog.ai.images.quality', $validated['ai_images_quality'] ?? 'medium');
        $this->setText('blog.ai.images.background', $validated['ai_images_background'] ?? 'opaque');
        $this->setText('blog.ai.images.output_format', $validated['ai_images_output_format'] ?? 'webp');
        $this->setText('blog.ai.images.output_compression', isset($validated['ai_images_output_compression']) ? (string) $validated['ai_images_output_compression'] : '85');

        $this->setText('blog.ai.images.default_cover_count', isset($validated['ai_images_default_cover_count']) ? (string) $validated['ai_images_default_cover_count'] : '1');
        $this->setText('blog.ai.images.default_inline_count', isset($validated['ai_images_default_inline_count']) ? (string) $validated['ai_images_default_inline_count'] : '2');

        $this->setText('blog.ai.images.cover_target_size', $validated['ai_images_cover_target_size'] ?? '1200x630');
        $this->setText('blog.ai.images.inline_target_size_43', $validated['ai_images_inline_target_size_43'] ?? '960x720');
        $this->setText('blog.ai.images.inline_target_size_square', $validated['ai_images_inline_target_size_square'] ?? '768x768');

        $this->setText('blog.ai.images.cost_cover_usd', isset($validated['ai_images_cost_cover_usd']) ? (string) $validated['ai_images_cost_cover_usd'] : '0.02');
        $this->setText('blog.ai.images.cost_inline_usd', isset($validated['ai_images_cost_inline_usd']) ? (string) $validated['ai_images_cost_inline_usd'] : '0.01');

        $this->setText('blog.ai.images.style', $validated['ai_images_style'] ?? 'Плоская современная иллюстрация, чистые формы, мягкие тени, дружелюбный стиль, без текста.');
        $this->setText('blog.ai.images.palette', $validated['ai_images_palette'] ?? '#1F6FEB,#F97316,#111827,#FFFFFF');
        $this->setText('blog.ai.images.rules', $validated['ai_images_rules'] ?? "Запрещено: любой текст, буквы, цифры, логотипы, водяные знаки, надписи на вывесках.\nРазрешено: персонажи/люди в виде иллюстраций.\nСтиль: единый, аккуратный, без агрессии.");

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

