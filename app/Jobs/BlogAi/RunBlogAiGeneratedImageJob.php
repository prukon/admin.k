<?php

namespace App\Jobs\BlogAi;

use App\Models\BlogAiGeneratedImage;
use App\Models\BlogAiGeneration;
use App\Models\BlogPost;
use App\Services\BlogAi\BlogAiHtmlImageInserter;
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

class RunBlogAiGeneratedImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $generationId,
        public readonly int $imageId,
    ) {
    }

    public function handle(
        OpenAiClient $client,
        BlogAiImageSettings $imageSettings,
        BlogAiHtmlImageInserter $htmlInserter,
        BlogAiLimiter $limiter,
    ): void {
        /** @var BlogAiGeneration|null $gen */
        $gen = BlogAiGeneration::query()->find($this->generationId);
        /** @var BlogAiGeneratedImage|null $imgRow */
        $imgRow = BlogAiGeneratedImage::query()->find($this->imageId);

        if (!$gen || !$imgRow || (int) $imgRow->blog_ai_generation_id !== (int) $gen->id) {
            return;
        }

        $reservedUsd = (float) ($gen->reserved_usd ?? 0);
        $budgetDate = $gen->budget_date?->format('Y-m-d') ?: ($gen->created_at?->format('Y-m-d') ?? null);
        $spentUsd = 0.0;
        if ($gen->cost_text_usd) {
            $spentUsd += (float) $gen->cost_text_usd;
        }
        if ($gen->cost_images_usd) {
            $spentUsd += (float) $gen->cost_images_usd;
        }

        try {
            /** @var BlogPost|null $post */
            $post = $gen->blog_post_id ? BlogPost::query()->find($gen->blog_post_id) : null;
            if (!$post) {
                throw new \RuntimeException('Статья не найдена (возможно удалена).');
            }

            $imgRow->update([
                'status' => 'running',
                'error_message' => null,
            ]);

            $gen->update([
                'status' => 'running',
                'phase' => $imgRow->kind === 'cover' ? 'cover' : 'inline',
                'progress' => max(60, (int) ($gen->progress ?? 0)),
            ]);

            $manager = ImageManager::gd();

            // Ensure directories exist
            Storage::disk('public')->makeDirectory('blog/covers');
            Storage::disk('public')->makeDirectory('blog/inline');

            if ($imgRow->kind === 'cover') {
                $bytes = $this->generateImageBytes($client, (string) $imgRow->prompt, $imageSettings, '1536x1024');
                $target = $this->parseSize($imageSettings->coverTargetSize(), 1200, 630);

                $img = $manager->read($bytes)->coverDown($target['w'], $target['h']);
                $out = $this->encodeImage($img, $imageSettings);

                $ext = $imageSettings->outputFormat();
                $path = 'blog/covers/ai-' . $post->id . '-' . Str::uuid()->toString() . '.' . $ext;

                $this->putOrFail($path, $out);

                $post->update(['cover_image_path' => $path]);
                $imgRow->update([
                    'status' => 'succeeded',
                    'previous_path' => $imgRow->path,
                    'path' => $path,
                    'width' => $target['w'],
                    'height' => $target['h'],
                    'output_format' => $ext,
                    'cost_usd' => $imgRow->cost_usd ?? $imageSettings->costCoverUsd(),
                ]);
            } else {
                $aspect = ($imgRow->aspect === '1:1') ? '1:1' : '4:3';
                $size = $aspect === '1:1' ? '1024x1024' : '1536x1024';

                $bytes = $this->generateImageBytes($client, (string) $imgRow->prompt, $imageSettings, $size);

                $target = $aspect === '1:1'
                    ? $this->parseSize($imageSettings->inlineTargetSizeSquare(), 768, 768)
                    : $this->parseSize($imageSettings->inlineTargetSize43(), 960, 720);

                $img = $manager->read($bytes)->coverDown($target['w'], $target['h']);
                $out = $this->encodeImage($img, $imageSettings);

                $ext = $imageSettings->outputFormat();
                $path = 'blog/inline/ai-' . $post->id . '-' . Str::uuid()->toString() . '.' . $ext;

                $this->putOrFail($path, $out);

                $newUrl = asset('storage/' . $path);
                $oldUrl = $imgRow->path ? asset('storage/' . $imgRow->path) : null;

                $content = (string) $post->content;
                if ($oldUrl && str_contains($content, $oldUrl)) {
                    $content = str_replace($oldUrl, $newUrl, $content);
                } else {
                    $totalInline = BlogAiGeneratedImage::query()
                        ->where('blog_ai_generation_id', $gen->id)
                        ->where('kind', 'inline')
                        ->count();
                    $inlineIndex = BlogAiGeneratedImage::query()
                        ->where('blog_ai_generation_id', $gen->id)
                        ->where('kind', 'inline')
                        ->where('id', '<=', $imgRow->id)
                        ->count() - 1;

                    $content = $htmlInserter->insertInlineImage(
                        $content,
                        $inlineIndex,
                        $totalInline,
                        $newUrl,
                        (string) ($imgRow->alt ?? '')
                    );
                }
                $content = Purifier::clean($content);
                $post->update(['content' => $content]);

                $imgRow->update([
                    'status' => 'succeeded',
                    'previous_path' => $imgRow->path,
                    'path' => $path,
                    'width' => $target['w'],
                    'height' => $target['h'],
                    'output_format' => $ext,
                    'cost_usd' => $imgRow->cost_usd ?? $imageSettings->costInlineUsd(),
                ]);
            }

            // Accumulate image costs on parent generation
            $gen->refresh();
            $newCostImages = (float) ($gen->cost_images_usd ?? 0) + (float) ($imgRow->cost_usd ?? 0);
            $gen->update([
                'cost_images_usd' => round($newCostImages, 4),
                'progress' => min(95, max(70, (int) ($gen->progress ?? 70) + 10)),
            ]);

            // Dispatch next queued image or finish
            $next = BlogAiGeneratedImage::query()
                ->where('blog_ai_generation_id', $gen->id)
                ->where('status', 'queued')
                ->orderBy('id')
                ->first();

            if ($next) {
                dispatch(new self($gen->id, (int) $next->id));
                return;
            }

            $gen->refresh();
            $total = (float) ($gen->cost_text_usd ?? 0) + (float) ($gen->cost_images_usd ?? 0);

            $gen->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'error_message' => null,
                'phase' => 'done',
                'progress' => 100,
                'cost_total_usd' => round($total, 4),
                'cost_usd' => round($total, 4),
            ]);

            $limiter->finalize($budgetDate, $reservedUsd, $total);
        } catch (OpenAiRequestException $e) {
            $this->failGeneration($gen, $imgRow, $e->getMessage(), $limiter, $budgetDate, $reservedUsd, $spentUsd);
        } catch (Throwable $e) {
            Log::warning('Blog AI image job failed', [
                'generation_id' => $gen->id ?? null,
                'image_id' => $imgRow->id ?? null,
                'error' => $e->getMessage(),
            ]);
            $this->failGeneration($gen, $imgRow, $e->getMessage(), $limiter, $budgetDate, $reservedUsd, $spentUsd);
        }
    }

    private function failGeneration(
        BlogAiGeneration $gen,
        BlogAiGeneratedImage $imgRow,
        string $message,
        BlogAiLimiter $limiter,
        ?string $budgetDate,
        float $reservedUsd,
        float $spentUsd,
    ): void {
        $imgRow->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);

        BlogAiGeneratedImage::query()
            ->where('blog_ai_generation_id', $gen->id)
            ->where('status', 'queued')
            ->update([
                'status' => 'failed',
                'error_message' => $message,
                'updated_at' => now(),
            ]);

        $gen->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $message,
            'phase' => 'done',
            'progress' => 100,
        ]);

        $limiter->finalize($budgetDate, $reservedUsd, $spentUsd);
    }

    private function putOrFail(string $path, string $bytes): void
    {
        $ok = Storage::disk('public')->put($path, $bytes);
        if (!$ok || !Storage::disk('public')->exists($path)) {
            throw new \RuntimeException('Не удалось сохранить изображение в storage (проверьте права на storage/app/public/blog).');
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

