<?php

namespace App\Jobs\BlogAi;

use App\Models\BlogAiGeneratedImage;
use App\Models\BlogAiGeneration;
use App\Models\BlogPost;
use App\Services\BlogAi\BlogAiLimiter;
use App\Services\BlogAi\BlogAiImagePromptBuilder;
use App\Services\BlogAi\BlogAiImageSettings;
use App\Services\BlogAi\BlogAiSettings;
use App\Services\BlogAi\Exceptions\OpenAiRequestException;
use App\Services\BlogAi\OpenAiClient;
use App\Jobs\BlogAi\RunBlogAiGeneratedImageJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Mews\Purifier\Facades\Purifier;
use Illuminate\Database\QueryException;
use Throwable;

class RunBlogAiGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $generationId,
    ) {
    }

    public function handle(
        OpenAiClient $client,
        BlogAiSettings $settings,
        BlogAiImageSettings $imageSettings,
        BlogAiImagePromptBuilder $imagePromptBuilder,
        BlogAiLimiter $limiter
    ): void
    {
        /** @var BlogAiGeneration|null $gen */
        $gen = BlogAiGeneration::query()->find($this->generationId);
        if (!$gen) {
            return;
        }

        $reservedUsd = (float) ($gen->reserved_usd ?? 0);
        $budgetDate = $gen->budget_date?->format('Y-m-d') ?: ($gen->created_at?->format('Y-m-d') ?? null);
        $spentUsd = 0.0;

        try {
            $gen->update([
                'status' => 'running',
                'started_at' => now(),
                'progress' => 5,
                'phase' => 'text',
            ]);

            $payload = $this->buildOpenAiPayload($gen);
            $gen->update(['request_payload' => $payload]);

            $resp = $client->responses($payload);
            $gen->update(['response_raw' => json_encode($resp, JSON_UNESCAPED_UNICODE)]);

            $usageIn = (int) (data_get($resp, 'usage.input_tokens') ?? 0);
            $usageOut = (int) (data_get($resp, 'usage.output_tokens') ?? 0);
            $costTextUsd = $this->calcCostUsd($usageIn, $usageOut, $settings);
            $spentUsd += $costTextUsd;

            $gen->update([
                'usage_input_tokens' => $usageIn ?: null,
                'usage_output_tokens' => $usageOut ?: null,
                'cost_text_usd' => $costTextUsd > 0 ? $costTextUsd : null,
            ]);

            $incompleteReason = (string) (data_get($resp, 'incomplete_details.reason') ?? '');
            if ((string) (data_get($resp, 'status') ?? '') === 'incomplete' && $incompleteReason === 'max_output_tokens') {
                $this->failGeneration($gen, 'Ответ ИИ получился слишком длинным и был обрезан (max output tokens). Увеличьте Max output tokens в настройках блога или попросите более короткую статью.');
                $limiter->finalize($budgetDate, $reservedUsd, $spentUsd);
                return;
            }

            $outputText = $this->extractOutputText($resp);
            $parsed = $this->extractJsonObject($outputText);
            $data = json_decode($parsed, true);
            if (!is_array($data)) {
                throw new \RuntimeException('ИИ вернул некорректный JSON. Попробуйте перегенерацию.');
            }

            $gen->update([
                'response_json' => $data,
                'progress' => 45,
            ]);

            $post = $this->applyResultToPost($gen, $data);

            if (
                $gen->action === 'new_post'
                && $post
                && $imageSettings->enabled()
                && ((bool) $gen->want_cover_image || (int) $gen->inline_images_count > 0)
            ) {
                $gen->update([
                    'phase' => 'cover',
                    'progress' => 55,
                ]);

                $this->queueImagesForGeneration($gen, $post, $data);

                $first = BlogAiGeneratedImage::query()
                    ->where('blog_ai_generation_id', $gen->id)
                    ->where('status', 'queued')
                    ->orderBy('id')
                    ->first();

                if ($first) {
                    dispatch(new RunBlogAiGeneratedImageJob($gen->id, (int) $first->id));
                }

                // Do not finalize budget here; image jobs will finish generation and finalize.
                return;
            }

            $gen->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'error_message' => null,
                'phase' => 'done',
                'progress' => 100,
                'cost_images_usd' => null,
                'cost_total_usd' => round($costTextUsd, 4),
                'cost_usd' => round($costTextUsd, 4),
            ]);

            $limiter->finalize($budgetDate, $reservedUsd, $spentUsd);
        } catch (OpenAiRequestException $e) {
            $msg = $e->getMessage();
            $this->failGeneration($gen, $msg);
            $limiter->finalize($budgetDate, $reservedUsd, $spentUsd);
        } catch (Throwable $e) {
            Log::warning('Blog AI generation failed', [
                'generation_id' => $gen->id,
                'error' => $e->getMessage(),
            ]);
            $this->failGeneration($gen, $e->getMessage());
            $limiter->finalize($budgetDate, $reservedUsd, $spentUsd);
        }
    }

    private function failGeneration(BlogAiGeneration $gen, string $message): void
    {
        $gen->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $message,
            'progress' => 100,
        ]);
    }

    private function buildOpenAiPayload(BlogAiGeneration $gen): array
    {
        $template = (string) $gen->prompt_template_snapshot;
        $promptUser = (string) $gen->prompt_user;

        $action = (string) $gen->action;
        $actionHint = match ($action) {
            'improve' => "Действие: Улучши текст, сделай его яснее и полезнее, сохранив смысл. Улучши структуру и примеры.",
            'seo' => "Действие: Сделай статью более SEO: улучшай meta_title/meta_description, заголовки, структуру и добавь релевантные подзаголовки.",
            'checklist' => "Действие: Добавь в статью чек-лист и таблицу (Bootstrap classes) по теме. Не делай текст длиннее без необходимости.",
            'regenerate' => "Действие: Перегенерируй статью заново по теме, можно изменить структуру и примеры.",
            default => "Действие: Создай новую статью.",
        };

        $postContext = '';
        if ($gen->blog_post_id) {
            $post = BlogPost::query()->find($gen->blog_post_id);
            if ($post) {
                $content = (string) ($post->content ?? '');
                if (mb_strlen($content, 'UTF-8') > 12000) {
                    $content = mb_substr($content, 0, 12000) . "\n\n(…текст обрезан для лимита контекста)";
                }
                $postContext = "\n\nТекущая статья (для редактирования):\n"
                    . "TITLE: {$post->title}\n"
                    . "SLUG: {$post->slug}\n"
                    . "EXCERPT: " . ($post->excerpt ?? '') . "\n"
                    . "CONTENT_HTML:\n" . $content;
            }
        }

        $system = str_replace('{prompt}', $promptUser, $template);
        $system = trim($system . "\n\n" . $actionHint . $postContext);

        if (
            $gen->action === 'new_post'
            && ((bool) $gen->want_cover_image || (int) $gen->inline_images_count > 0)
        ) {
            $n = max(0, min(3, (int) $gen->inline_images_count));
            $system .= "\n\nИзображения для статьи:\n"
                . "- Верни cover_image_prompt (string) — описание обложки (иллюстрация).\n"
                . "- Верни inline_images (array из {$n} элементов) — для каждого элемента: prompt (string), alt (string), aspect (\"4:3\" или \"1:1\").\n"
                . "Строго: в описаниях указывай, что на картинках НЕ должно быть текста/букв/цифр/логотипов.\n";
        }

        return [
            'model' => (string) $gen->model,
            // Use plain string input for maximum compatibility with Responses API.
            'input' => $system,
            'max_output_tokens' => (int) $gen->max_output_tokens,
            'text' => [
                'format' => [
                    'type' => 'json_object',
                ],
            ],
        ];
    }

    private function extractOutputText(array $resp): string
    {
        $ot = (string) (data_get($resp, 'output_text') ?? '');
        if ($ot !== '') {
            return $ot;
        }

        $output = data_get($resp, 'output', []);
        if (is_array($output)) {
            foreach ($output as $item) {
                $content = data_get($item, 'content', []);
                if (!is_array($content)) {
                    continue;
                }
                foreach ($content as $c) {
                    $t = (string) (data_get($c, 'text') ?? '');
                    if ($t !== '') {
                        return $t;
                    }
                }
            }
        }

        return '';
    }

    private function extractJsonObject(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('ИИ вернул пустой ответ.');
        }

        // Fast path: already JSON.
        if (str_starts_with($text, '{') && str_ends_with($text, '}')) {
            return $text;
        }

        // Try to find first balanced {...} block.
        $start = strpos($text, '{');
        if ($start === false) {
            throw new \RuntimeException('ИИ не вернул JSON. Попробуйте перегенерацию.');
        }

        $level = 0;
        $inString = false;
        $escape = false;
        for ($i = $start; $i < strlen($text); $i++) {
            $ch = $text[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }
            if ($ch === '{') {
                $level++;
            } elseif ($ch === '}') {
                $level--;
                if ($level === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        throw new \RuntimeException('Не удалось распарсить JSON от ИИ. Попробуйте перегенерацию.');
    }

    private function calcCostUsd(int $inputTokens, int $outputTokens, BlogAiSettings $settings): float
    {
        $inPrice = $settings->priceInputPer1m();
        $outPrice = $settings->priceOutputPer1m();
        if (($inPrice ?? 0) <= 0 || ($outPrice ?? 0) <= 0) {
            return 0.0;
        }

        $usd = ($inputTokens / 1_000_000) * (float) $inPrice
            + ($outputTokens / 1_000_000) * (float) $outPrice;

        return (float) round($usd, 4);
    }

    private function applyResultToPost(BlogAiGeneration $gen, array $data): ?BlogPost
    {
        $title = trim((string) ($data['title'] ?? ''));
        $excerpt = (string) ($data['excerpt'] ?? '');
        $content = (string) ($data['content_html'] ?? '');

        if ($title === '' || $content === '') {
            throw new \RuntimeException('ИИ вернул неполные данные (title/content). Попробуйте перегенерацию.');
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $slug = $this->makeUniqueSlug($title, $gen->blog_post_id);
        } else {
            $slug = $this->makeUniqueSlug($slug, $gen->blog_post_id, true);
        }

        $excerpt = $excerpt !== '' ? mb_substr($excerpt, 0, 700) : null;
        $metaTitle = isset($data['meta_title']) ? (string) $data['meta_title'] : null;
        $metaDesc = isset($data['meta_description']) ? (string) $data['meta_description'] : null;
        $canonical = isset($data['canonical_url']) ? (string) $data['canonical_url'] : null;

        $cleanContent = Purifier::clean($content);
        $cleanExcerpt = $excerpt !== null ? Purifier::clean($excerpt) : null;

        if ($gen->action === 'new_post') {
            $payload = [
                'blog_category_id' => (int) $gen->blog_category_id,
                'title' => mb_substr($title, 0, 255),
                'slug' => mb_substr($slug, 0, 255),
                'excerpt' => $cleanExcerpt,
                'content' => $cleanContent,
                'meta_title' => $metaTitle !== '' ? mb_substr($metaTitle, 0, 255) : null,
                'meta_description' => $metaDesc !== '' ? mb_substr($metaDesc, 0, 500) : null,
                'canonical_url' => $canonical !== '' ? mb_substr($canonical, 0, 500) : null,
                'is_published' => false,
                // Проставляем сразу при генерации (по МСК), даже если пост остаётся черновиком.
                'published_at' => now('Europe/Moscow'),
            ];

            try {
                $post = BlogPost::query()->create($payload);
            } catch (QueryException $e) {
                // Rare race or soft-deleted collision: regenerate slug and retry once.
                if (str_contains((string) $e->getMessage(), 'blog_posts_slug_unique')) {
                    $payload['slug'] = mb_substr($this->makeUniqueSlug($title, null, false), 0, 255);
                    $post = BlogPost::query()->create($payload);
                } else {
                    throw $e;
                }
            }

            // Safety net: если по какой-то причине published_at не записался — проставим его явно.
            if ($post->published_at === null) {
                $post->forceFill(['published_at' => now('Europe/Moscow')])->save();
            }

            $gen->update(['blog_post_id' => $post->id]);
            return $post;
        }

        if (!$gen->blog_post_id) {
            throw new \RuntimeException('Не найден пост для обновления.');
        }

        $post = BlogPost::query()->find($gen->blog_post_id);
        if (!$post) {
            throw new \RuntimeException('Пост не найден (возможно удалён).');
        }

        $updatePayload = [
            'title' => mb_substr($title, 0, 255),
            'slug' => mb_substr($slug, 0, 255),
            'excerpt' => $cleanExcerpt,
            'content' => $cleanContent,
            'meta_title' => $metaTitle !== '' ? mb_substr($metaTitle, 0, 255) : null,
            'meta_description' => $metaDesc !== '' ? mb_substr($metaDesc, 0, 500) : null,
            'canonical_url' => $canonical !== '' ? mb_substr($canonical, 0, 500) : null,
        ];

        try {
            $post->update($updatePayload);
        } catch (QueryException $e) {
            if (str_contains((string) $e->getMessage(), 'blog_posts_slug_unique')) {
                $updatePayload['slug'] = mb_substr($this->makeUniqueSlug($title, (int) $post->id, false), 0, 255);
                $post->update($updatePayload);
            } else {
                throw $e;
            }
        }

        return $post;
    }

    private function queueImagesForGeneration(BlogAiGeneration $gen, BlogPost $post, array $data): void
    {
        $imageSettings = app(BlogAiImageSettings::class);
        $promptBuilder = app(BlogAiImagePromptBuilder::class);

        if ((bool) $gen->want_cover_image) {
            $coverPrompt = (string) ($data['cover_image_prompt'] ?? '');
            $coverPrompt = $promptBuilder->build(
                (string) $post->title,
                'Обложка статьи',
                $coverPrompt !== '' ? $coverPrompt : ($post->excerpt ? (string) $post->excerpt : null)
            );

            BlogAiGeneratedImage::query()->create([
                'blog_ai_generation_id' => (int) $gen->id,
                'blog_post_id' => (int) $post->id,
                'kind' => 'cover',
                'aspect' => 'og',
                'prompt' => $coverPrompt,
                'alt' => (string) ($post->title ?? ''),
                'status' => 'queued',
                'output_format' => $imageSettings->outputFormat(),
                'cost_usd' => $imageSettings->costCoverUsd(),
            ]);
        }

        $inlineCount = max(0, min(3, (int) $gen->inline_images_count));
        if ($inlineCount <= 0) {
            return;
        }

        $inline = is_array($data['inline_images'] ?? null) ? (array) $data['inline_images'] : [];
        $headings = $this->extractH2Texts((string) $post->content);

        for ($i = 0; $i < $inlineCount; $i++) {
            $aspect = (($inline[$i]['aspect'] ?? null) === '1:1') ? '1:1' : '4:3';
            if ($aspect !== '1:1' && $i === 1) {
                $aspect = '1:1';
            }

            $topic = $headings[$i] ?? (string) $post->title;
            $extra = is_array($inline[$i] ?? null) ? (string) (($inline[$i]['prompt'] ?? '') ?: '') : '';
            $alt = is_array($inline[$i] ?? null) ? (string) (($inline[$i]['alt'] ?? '') ?: '') : '';
            if ($alt === '') {
                $alt = $topic !== '' ? $topic : (string) $post->title;
            }
            if (mb_strlen($alt, 'UTF-8') > 255) {
                $alt = mb_substr($alt, 0, 255);
            }

            $purpose = 'Иллюстрация внутри статьи (после секции ' . ($i + 1) . ')';
            $prompt = $promptBuilder->build($topic, $purpose, $extra);

            BlogAiGeneratedImage::query()->create([
                'blog_ai_generation_id' => (int) $gen->id,
                'blog_post_id' => (int) $post->id,
                'kind' => 'inline',
                'aspect' => $aspect,
                'prompt' => $prompt,
                'alt' => $alt,
                'status' => 'queued',
                'output_format' => $imageSettings->outputFormat(),
                'cost_usd' => $imageSettings->costInlineUsd(),
            ]);
        }
    }

    private function generateImageBytes(OpenAiClient $client, string $prompt, BlogAiImageSettings $settings, string $size): string
    {
        $payload = [
            'model' => $settings->model(),
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => $settings->quality(),
            'background' => $settings->background(),
            'output_format' => $settings->outputFormat(),
            'output_compression' => $settings->outputCompression(),
            'moderation' => 'auto',
        ];

        $resp = $client->imagesGenerations($payload);
        $b64 = (string) (data_get($resp, 'data.0.b64_json') ?? '');
        if ($b64 === '') {
            throw new \RuntimeException('OpenAI не вернул изображение (b64_json).');
        }

        $bytes = base64_decode($b64, true);
        if ($bytes === false) {
            throw new \RuntimeException('Не удалось декодировать изображение (base64).');
        }

        return $bytes;
    }

    private function encodeImage(\Intervention\Image\Interfaces\ImageInterface $img, BlogAiImageSettings $settings): string
    {
        $fmt = $settings->outputFormat();
        $q = $settings->outputCompression();

        if ($fmt === 'png') {
            return (string) $img->toPng();
        }
        if ($fmt === 'jpeg') {
            return (string) $img->toJpeg($q);
        }
        // webp
        return (string) $img->toWebp($q);
    }

    private function parseSize(string $value, int $defaultW, int $defaultH): array
    {
        if (preg_match('/^(\d{2,4})x(\d{2,4})$/', trim($value), $m)) {
            return [
                'w' => max(1, (int) $m[1]),
                'h' => max(1, (int) $m[2]),
            ];
        }
        return ['w' => $defaultW, 'h' => $defaultH];
    }

    private function extractH2Texts(string $html): array
    {
        $texts = [];
        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . '<div>' . $html . '</div>');
        libxml_clear_errors();

        $h2s = $doc->getElementsByTagName('h2');
        foreach ($h2s as $h2) {
            $t = trim((string) $h2->textContent);
            if ($t !== '') {
                $texts[] = $t;
            }
        }
        return $texts;
    }

    private function insertInlineImageAfterH2(string $html, int $h2Index, string $imgUrl, string $alt): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . '<div id="root">' . $html . '</div>');
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $h2Nodes = $xpath->query('//div[@id="root"]//h2');
        if (!$h2Nodes || $h2Nodes->length === 0 || $h2Index >= $h2Nodes->length) {
            return $html . $this->inlineFigureHtml($imgUrl, $alt);
        }

        $h2 = $h2Nodes->item($h2Index);
        if (!$h2 || !$h2->parentNode) {
            return $html . $this->inlineFigureHtml($imgUrl, $alt);
        }

        $figure = $doc->createElement('figure');
        $figure->setAttribute('class', 'my-3');

        $img = $doc->createElement('img');
        $img->setAttribute('src', $imgUrl);
        $img->setAttribute('alt', $alt);
        $img->setAttribute('class', 'img-fluid rounded');
        $figure->appendChild($img);

        // insert after h2
        if ($h2->nextSibling) {
            $h2->parentNode->insertBefore($figure, $h2->nextSibling);
        } else {
            $h2->parentNode->appendChild($figure);
        }

        $root = $xpath->query('//div[@id="root"]')->item(0);
        $out = '';
        if ($root) {
            foreach ($root->childNodes as $child) {
                $out .= $doc->saveHTML($child);
            }
        }

        return $out !== '' ? $out : $html;
    }

    private function inlineFigureHtml(string $imgUrl, string $alt): string
    {
        $altEsc = htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $urlEsc = htmlspecialchars($imgUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<figure class="my-3">'
            . '<img src="' . $urlEsc . '" alt="' . $altEsc . '" class="img-fluid rounded">'
            . '</figure>';
    }

    private function makeUniqueSlug(string $value, ?int $ignoreId = null, bool $valueIsSlug = false): string
    {
        $base = $valueIsSlug ? $value : Str::slug($value);
        $slug = $base !== '' ? $base : 'post';

        $i = 1;
        while (BlogPost::withTrashed()
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

