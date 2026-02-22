@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        @include('admin.blog._toolbar', ['active' => 'settings'])

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form action="{{ route('admin.blog.settings.update') }}" method="POST" enctype="multipart/form-data" class="row g-3">
            @csrf

            <div class="col-12 col-lg-6">
                <label class="form-label">SEO Title (страница /blog)</label>
                <input type="text"
                       name="index_meta_title"
                       value="{{ old('index_meta_title', $settings['index_meta_title'] ?? '') }}"
                       class="form-control @error('index_meta_title') is-invalid @enderror">
                @error('index_meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">Если пусто — используем дефолт “Блог — kidscrm.online”.</div>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">SEO Description (страница /blog)</label>
                <input type="text"
                       name="index_meta_description"
                       value="{{ old('index_meta_description', $settings['index_meta_description'] ?? '') }}"
                       class="form-control @error('index_meta_description') is-invalid @enderror">
                @error('index_meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">Шаблон SEO Title (статья)</label>
                <input type="text"
                       name="post_title_template"
                       value="{{ old('post_title_template', $settings['post_title_template'] ?? '{title} — Блог | kidscrm.online') }}"
                       class="form-control @error('post_title_template') is-invalid @enderror">
                @error('post_title_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">Можно использовать переменную <code>{title}</code>.</div>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">OG‑изображение по умолчанию (для /blog и fallback)</label>
                <input type="file" name="default_og_image" class="form-control @error('default_og_image') is-invalid @enderror">
                @error('default_og_image')<div class="invalid-feedback">{{ $message }}</div>@enderror

                @if(!empty($settings['default_og_image_path']))
                    <div class="mt-2">
                        <div class="text-muted small mb-1">Текущее:</div>
                        <img src="{{ asset('storage/' . $settings['default_og_image_path']) }}"
                             alt="Default OG"
                             style="max-width: 280px; height: auto;">
                        <div class="text-muted small mt-1"><code>{{ $settings['default_og_image_path'] }}</code></div>
                    </div>
                @endif
            </div>

            <div class="col-12">
                <hr class="my-4">
                <div class="h5 mb-0">ИИ: генерация статей</div>
                <div class="text-muted small mt-1">
                    Настройки ниже используются для кнопки “Статья (ИИ)” и действий “Улучшить/SEO/Таблица‑чеклист”.
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Модель OpenAI</label>
                <input type="text"
                       name="ai_model"
                       value="{{ old('ai_model', $settings['ai_model'] ?? 'gpt-5.1') }}"
                       class="form-control @error('ai_model') is-invalid @enderror">
                @error('ai_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">Например: <code>gpt-5.1</code>.</div>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Бюджет в день ($)</label>
                <input type="number"
                       step="0.01"
                       name="ai_daily_budget_usd"
                       value="{{ old('ai_daily_budget_usd', $settings['ai_daily_budget_usd'] ?? '5') }}"
                       class="form-control @error('ai_daily_budget_usd') is-invalid @enderror">
                @error('ai_daily_budget_usd')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">Глобальный лимит. <code>0</code> — без ограничения.</div>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Max output tokens</label>
                <input type="number"
                       name="ai_max_output_tokens"
                       value="{{ old('ai_max_output_tokens', $settings['ai_max_output_tokens'] ?? '4500') }}"
                       class="form-control @error('ai_max_output_tokens') is-invalid @enderror">
                @error('ai_max_output_tokens')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">Ограничивает длину ответа и стоимость.</div>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">Цена input-токенов ($ за 1M)</label>
                <input type="number"
                       step="0.0001"
                       name="ai_price_input_per_1m"
                       value="{{ old('ai_price_input_per_1m', $settings['ai_price_input_per_1m'] ?? '') }}"
                       class="form-control @error('ai_price_input_per_1m') is-invalid @enderror">
                @error('ai_price_input_per_1m')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">
                    Нужна для расчёта лимита по $/день. Значения можно взять из OpenAI Pricing.
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">Цена output-токенов ($ за 1M)</label>
                <input type="number"
                       step="0.0001"
                       name="ai_price_output_per_1m"
                       value="{{ old('ai_price_output_per_1m', $settings['ai_price_output_per_1m'] ?? '') }}"
                       class="form-control @error('ai_price_output_per_1m') is-invalid @enderror">
                @error('ai_price_output_per_1m')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label">Шаблон промпта для статьи</label>
                <textarea name="ai_prompt_template"
                          rows="14"
                          class="form-control @error('ai_prompt_template') is-invalid @enderror">{{ old('ai_prompt_template', $settings['ai_prompt_template'] ?? '') }}</textarea>
                @error('ai_prompt_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">
                    Доступна переменная <code>{prompt}</code> — пользовательский запрос из модалки.
                </div>
            </div>

            <div class="col-12">
                <hr class="my-4">
                <div class="h5 mb-0">ИИ: изображения для статей</div>
                <div class="text-muted small mt-1">
                    Генерация иллюстраций (1 обложка + 2–3 внутри). Изображения вставляются в статью автоматически.
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Включить изображения</label>
                <select name="ai_images_enabled" class="form-select @error('ai_images_enabled') is-invalid @enderror">
                    @php($imgEnabled = old('ai_images_enabled', $settings['ai_images_enabled'] ?? '1'))
                    <option value="1" @selected((string)$imgEnabled === '1')>Да</option>
                    <option value="0" @selected((string)$imgEnabled === '0')>Нет</option>
                </select>
                @error('ai_images_enabled')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Модель изображений</label>
                <input type="text"
                       name="ai_images_model"
                       value="{{ old('ai_images_model', $settings['ai_images_model'] ?? 'gpt-image-1') }}"
                       class="form-control @error('ai_images_model') is-invalid @enderror">
                @error('ai_images_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Качество</label>
                <select name="ai_images_quality" class="form-select @error('ai_images_quality') is-invalid @enderror">
                    @php($imgQ = old('ai_images_quality', $settings['ai_images_quality'] ?? 'medium'))
                    <option value="auto" @selected($imgQ==='auto')>auto</option>
                    <option value="low" @selected($imgQ==='low')>low</option>
                    <option value="medium" @selected($imgQ==='medium')>medium</option>
                    <option value="high" @selected($imgQ==='high')>high</option>
                </select>
                @error('ai_images_quality')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Фон</label>
                <select name="ai_images_background" class="form-select @error('ai_images_background') is-invalid @enderror">
                    @php($imgBg = old('ai_images_background', $settings['ai_images_background'] ?? 'opaque'))
                    <option value="auto" @selected($imgBg==='auto')>auto</option>
                    <option value="opaque" @selected($imgBg==='opaque')>opaque</option>
                    <option value="transparent" @selected($imgBg==='transparent')>transparent</option>
                </select>
                @error('ai_images_background')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Формат</label>
                <select name="ai_images_output_format" class="form-select @error('ai_images_output_format') is-invalid @enderror">
                    @php($imgFmt = old('ai_images_output_format', $settings['ai_images_output_format'] ?? 'webp'))
                    <option value="webp" @selected($imgFmt==='webp')>webp</option>
                    <option value="png" @selected($imgFmt==='png')>png</option>
                    <option value="jpeg" @selected($imgFmt==='jpeg')>jpeg</option>
                </select>
                @error('ai_images_output_format')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Сжатие WEBP/JPEG (%)</label>
                <input type="number"
                       name="ai_images_output_compression"
                       value="{{ old('ai_images_output_compression', $settings['ai_images_output_compression'] ?? '85') }}"
                       class="form-control @error('ai_images_output_compression') is-invalid @enderror">
                @error('ai_images_output_compression')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Дефолт: обложка (шт.)</label>
                <select name="ai_images_default_cover_count" class="form-select @error('ai_images_default_cover_count') is-invalid @enderror">
                    @php($defCover = old('ai_images_default_cover_count', $settings['ai_images_default_cover_count'] ?? '1'))
                    <option value="0" @selected((string)$defCover==='0')>0</option>
                    <option value="1" @selected((string)$defCover==='1')>1</option>
                </select>
                @error('ai_images_default_cover_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Дефолт: изображения внутри (шт.)</label>
                <select name="ai_images_default_inline_count" class="form-select @error('ai_images_default_inline_count') is-invalid @enderror">
                    @php($defInline = old('ai_images_default_inline_count', $settings['ai_images_default_inline_count'] ?? '2'))
                    @for($i=0;$i<=3;$i++)
                        <option value="{{ $i }}" @selected((string)$defInline === (string)$i)>{{ $i }}</option>
                    @endfor
                </select>
                @error('ai_images_default_inline_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Обложка: целевой размер</label>
                <input type="text"
                       name="ai_images_cover_target_size"
                       value="{{ old('ai_images_cover_target_size', $settings['ai_images_cover_target_size'] ?? '1200x630') }}"
                       class="form-control @error('ai_images_cover_target_size') is-invalid @enderror">
                @error('ai_images_cover_target_size')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">Мы генерируем в поддерживаемом размере и затем кадрируем/ресайзим.</div>
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Inline (4:3): целевой размер</label>
                <input type="text"
                       name="ai_images_inline_target_size_43"
                       value="{{ old('ai_images_inline_target_size_43', $settings['ai_images_inline_target_size_43'] ?? '960x720') }}"
                       class="form-control @error('ai_images_inline_target_size_43') is-invalid @enderror">
                @error('ai_images_inline_target_size_43')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-4">
                <label class="form-label">Inline (1:1): целевой размер</label>
                <input type="text"
                       name="ai_images_inline_target_size_square"
                       value="{{ old('ai_images_inline_target_size_square', $settings['ai_images_inline_target_size_square'] ?? '768x768') }}"
                       class="form-control @error('ai_images_inline_target_size_square') is-invalid @enderror">
                @error('ai_images_inline_target_size_square')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">Стоимость обложки ($)</label>
                <input type="number"
                       step="0.0001"
                       name="ai_images_cost_cover_usd"
                       value="{{ old('ai_images_cost_cover_usd', $settings['ai_images_cost_cover_usd'] ?? '0.02') }}"
                       class="form-control @error('ai_images_cost_cover_usd') is-invalid @enderror">
                @error('ai_images_cost_cover_usd')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">Нужно для общего лимита $/день (текст + изображения).</div>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">Стоимость inline-изображения ($)</label>
                <input type="number"
                       step="0.0001"
                       name="ai_images_cost_inline_usd"
                       value="{{ old('ai_images_cost_inline_usd', $settings['ai_images_cost_inline_usd'] ?? '0.01') }}"
                       class="form-control @error('ai_images_cost_inline_usd') is-invalid @enderror">
                @error('ai_images_cost_inline_usd')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">Стиль изображений (описание)</label>
                <textarea name="ai_images_style"
                          rows="5"
                          class="form-control @error('ai_images_style') is-invalid @enderror">{{ old('ai_images_style', $settings['ai_images_style'] ?? '') }}</textarea>
                @error('ai_images_style')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">Палитра (HEX, через запятую)</label>
                <textarea name="ai_images_palette"
                          rows="5"
                          class="form-control @error('ai_images_palette') is-invalid @enderror">{{ old('ai_images_palette', $settings['ai_images_palette'] ?? '') }}</textarea>
                @error('ai_images_palette')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">Например: <code>#1F6FEB,#F97316,#111827,#FFFFFF</code>.</div>
            </div>

            <div class="col-12">
                <label class="form-label">Правила/запреты для изображений</label>
                <textarea name="ai_images_rules"
                          rows="6"
                          class="form-control @error('ai_images_rules') is-invalid @enderror">{{ old('ai_images_rules', $settings['ai_images_rules'] ?? '') }}</textarea>
                @error('ai_images_rules')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">
                    По умолчанию запрещаем любой текст/буквы/цифры/логотипы. Разрешаем персонажей (иллюстрации).
                </div>
            </div>

            <div class="col-12">
                <button class="btn btn-primary" type="submit">Сохранить</button>
            </div>
        </form>
    </div>
@endsection

