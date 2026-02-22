<?php

namespace App\Jobs\BlogAi;

use App\Models\BlogAiGeneratedImage;
use App\Models\BlogAiGeneration;
use App\Models\BlogPost;
use App\Services\BlogAi\BlogAiHtmlImageInserter;
use App\Services\BlogAi\BlogAiImagePromptBuilder;
use App\Services\BlogAi\BlogAiImageSettings;
use App\Services\BlogAi\BlogAiLimiter;
use App\Services\BlogAi\Exceptions\OpenAiRequestException;
use App\Services\BlogAi\OpenAiClient;
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
use Throwable;

class RunBlogAiImageRegenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $generationId,
    ) {
    }

    public function handle(
        OpenAiClient $client,
        BlogAiImageSettings $imageSettings,
        BlogAiImagePromptBuilder $promptBuilder,
        BlogAiHtmlImageInserter $htmlInserter,
        BlogAiLimiter $limiter,
    ): void {
        /** @var BlogAiGeneration|null $gen */
        $gen = BlogAiGeneration::query()->find($this->generationId);
        if (!$gen) {
            return;
        }

        $reservedUsd = (float) ($gen->reserved_usd ?? 0);
        $budgetDate = $gen->budget_date?->format('Y-m-d') ?: ($gen->created_at?->format('Y-m-d') ?? null);
        $spentUsd = 0.0;

        try {
            /** @var BlogAiGeneratedImage|null $imgRow */
            $imgRow = BlogAiGeneratedImage::query()->find($gen->blog_ai_generated_image_id);
            if (!$imgRow) {
                throw new \RuntimeException('Изображение не найдено.');
            }

            /** @var BlogPost|null $post */
            $post = $imgRow->blog_post_id ? BlogPost::query()->find($imgRow->blog_post_id) : null;
            if (!$post) {
                throw new \RuntimeException('Статья не найдена (возможно удалена).');
            }

            $gen->update([
                'status' => 'running',
                'started_at' => now(),
                'phase' => $imgRow->kind === 'cover' ? 'cover' : 'inline',
                'progress' => 20,
            ]);

            $imgRow->update([
                'status' => 'running',
                'error_message' => null,
            ]);

            $purpose = $imgRow->kind === 'cover' ? 'Обложка статьи' : 'Иллюстрация внутри статьи';
            $topic = $post->title ?: 'Статья';

            $extra = $imgRow->prompt;
            $promptExtra = trim((string) ($gen->prompt_user ?? ''));
            if ($promptExtra !== '' && $promptExtra !== '—') {
                $extra .= "\n\nДоп. указания пользователя:\n" . $promptExtra;
            }

            $prompt = $promptBuilder->build($topic, $purpose, $extra);

            $manager = ImageManager::gd();
            $oldPath = $imgRow->path;

            if ($imgRow->kind === 'cover') {
                $gen->update(['progress' => 45]);
                $bytes = $this->generateImageBytes($client, $prompt, $imageSettings, '1536x1024');
                $target = $this->parseSize($imageSettings->coverTargetSize(), 1200, 630);

                $img = $manager->read($bytes)->coverDown($target['w'], $target['h']);
                $out = $this->encodeImage($img, $imageSettings);

                $ext = $imageSettings->outputFormat();
                $path = 'blog/covers/ai-' . $post->id . '-' . Str::uuid()->toString() . '.' . $ext;
                Storage::disk('public')->put($path, $out);

                $post->update(['cover_image_path' => $path]);

                $imgRow->update([
                    'status' => 'succeeded',
                    'previous_path' => $oldPath,
                    'path' => $path,
                    'width' => $target['w'],
                    'height' => $target['h'],
                    'output_format' => $ext,
                ]);

                if ($oldPath && $oldPath !== $path) {
                    Storage::disk('public')->delete($oldPath);
                }

                $spentUsd = max(0, $imageSettings->costCoverUsd());
            } else {
                $gen->update(['progress' => 45]);
                $aspect = ($imgRow->aspect === '1:1') ? '1:1' : '4:3';
                $size = $aspect === '1:1' ? '1024x1024' : '1536x1024';
                $bytes = $this->generateImageBytes($client, $prompt, $imageSettings, $size);

                $target = $aspect === '1:1'
                    ? $this->parseSize($imageSettings->inlineTargetSizeSquare(), 768, 768)
                    : $this->parseSize($imageSettings->inlineTargetSize43(), 960, 720);

                $img = $manager->read($bytes)->coverDown($target['w'], $target['h']);
                $out = $this->encodeImage($img, $imageSettings);

                $ext = $imageSettings->outputFormat();
                $path = 'blog/inline/ai-' . $post->id . '-' . Str::uuid()->toString() . '.' . $ext;
                Storage::disk('public')->put($path, $out);

                $oldUrl = $oldPath ? asset('storage/' . $oldPath) : null;
                $newUrl = asset('storage/' . $path);
                $content = (string) $post->content;
                if ($oldUrl && str_contains($content, $oldUrl)) {
                    $content = str_replace($oldUrl, $newUrl, $content);
                } else {
                    $content = $htmlInserter->insertInlineImage(
                        $content,
                        0,
                        1,
                        $newUrl,
                        (string) ($imgRow->alt ?? '')
                    );
                }

                $content = Purifier::clean($content);
                $post->update(['content' => $content]);

                $imgRow->update([
                    'status' => 'succeeded',
                    'previous_path' => $oldPath,
                    'path' => $path,
                    'width' => $target['w'],
                    'height' => $target['h'],
                    'output_format' => $ext,
                ]);

                if ($oldPath && $oldPath !== $path) {
                    Storage::disk('public')->delete($oldPath);
                }

                $spentUsd = max(0, $imageSettings->costInlineUsd());
            }

            $imgRow->update(['cost_usd' => $spentUsd > 0 ? round($spentUsd, 4) : null]);

            $gen->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'error_message' => null,
                'phase' => 'done',
                'progress' => 100,
                'cost_images_usd' => $spentUsd > 0 ? round($spentUsd, 4) : null,
                'cost_total_usd' => round($spentUsd, 4),
                'cost_usd' => round($spentUsd, 4),
            ]);

            $limiter->finalize($budgetDate, $reservedUsd, $spentUsd);
        } catch (OpenAiRequestException $e) {
            $this->fail($gen, $e->getMessage());
            $limiter->finalize($budgetDate, $reservedUsd, $spentUsd);
        } catch (Throwable $e) {
            Log::warning('Blog AI image regen failed', [
                'generation_id' => $gen->id,
                'error' => $e->getMessage(),
            ]);
            $this->fail($gen, $e->getMessage());
            $limiter->finalize($budgetDate, $reservedUsd, $spentUsd);
        }
    }

    private function fail(BlogAiGeneration $gen, string $message): void
    {
        $gen->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $message,
            'progress' => 100,
            'phase' => 'done',
        ]);

        if ($gen->blog_ai_generated_image_id) {
            BlogAiGeneratedImage::query()
                ->whereKey($gen->blog_ai_generated_image_id)
                ->update([
                    'status' => 'failed',
                    'error_message' => $message,
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
}

