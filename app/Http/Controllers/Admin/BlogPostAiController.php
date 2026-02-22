<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\Admin\StartAiBlogPostActionRequest;
use App\Http\Requests\Blog\Admin\StartAiBlogPostRequest;
use App\Jobs\BlogAi\RunBlogAiGenerationJob;
use App\Models\BlogAiGeneration;
use App\Models\BlogAiGeneratedImage;
use App\Models\BlogPost;
use App\Services\BlogAi\BlogAiLimiter;
use App\Services\BlogAi\BlogAiImageSettings;
use App\Services\BlogAi\BlogAiSettings;
use App\Services\BlogAi\Exceptions\BlogAiBudgetException;
use Illuminate\Http\JsonResponse;

class BlogPostAiController extends Controller
{
    public function start(StartAiBlogPostRequest $request, BlogAiSettings $settings, BlogAiImageSettings $imageSettings, BlogAiLimiter $limiter): JsonResponse
    {
        $template = $settings->promptTemplate();
        if ($template === '') {
            return response()->json([
                'message' => 'В настройках блога не задан шаблон промпта для ИИ.',
                'errors' => ['prompt' => ['В настройках блога не задан шаблон промпта для ИИ.']],
            ], 422);
        }

        $model = $settings->model();
        $maxOutput = $settings->maxOutputTokens();

        $wantCover = (bool) $request->validated('want_cover_image');
        $inlineCount = (int) $request->validated('inline_images_count');

        if (($wantCover || $inlineCount > 0) && !$imageSettings->enabled()) {
            return response()->json([
                'message' => 'Генерация изображений отключена в настройках блога.',
                'errors' => ['prompt' => ['Генерация изображений отключена в настройках блога.']],
            ], 422);
        }

        $estimatedMaxUsd = $this->estimateMaxUsd(
            prompt: (string) $request->validated('prompt'),
            template: $template,
            maxOutputTokens: $maxOutput,
            inputPricePer1m: $settings->priceInputPer1m(),
            outputPricePer1m: $settings->priceOutputPer1m(),
            imageCostUsd: $this->estimateImagesUsd($wantCover, $inlineCount, $imageSettings),
            budgetUsd: $settings->dailyBudgetUsd(),
        );

        $budgetDate = now()->toDateString();
        try {
            $reservedUsd = $limiter->reserveOrFail($estimatedMaxUsd, $budgetDate);
        } catch (BlogAiBudgetException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['prompt' => [$e->getMessage()]],
            ], 422);
        }

        $gen = BlogAiGeneration::query()->create([
            'user_id' => (int) $request->user()->id,
            'blog_category_id' => (int) $request->validated('blog_category_id'),
            'action' => 'new_post',
            'status' => 'queued',
            'budget_date' => $budgetDate,
            'progress' => 0,
            'phase' => 'text',
            'want_cover_image' => $wantCover,
            'inline_images_count' => $inlineCount,
            'prompt_user' => (string) $request->validated('prompt'),
            'prompt_template_snapshot' => $template,
            'model' => $model,
            'max_output_tokens' => $maxOutput,
            'reserved_usd' => $reservedUsd > 0 ? $reservedUsd : null,
        ]);

        dispatch(new RunBlogAiGenerationJob($gen->id));

        return response()->json([
            'generation_id' => $gen->id,
            'status' => $gen->status,
        ]);
    }

    public function startForPost(BlogPost $post, StartAiBlogPostActionRequest $request, BlogAiSettings $settings, BlogAiLimiter $limiter): JsonResponse
    {
        $template = $settings->promptTemplate();
        if ($template === '') {
            return response()->json([
                'message' => 'В настройках блога не задан шаблон промпта для ИИ.',
                'errors' => ['prompt' => ['В настройках блога не задан шаблон промпта для ИИ.']],
            ], 422);
        }

        $model = $settings->model();
        $maxOutput = $settings->maxOutputTokens();

        $action = (string) $request->validated('action');
        $prompt = (string) ($request->validated('prompt') ?? '');

        $estimatedMaxUsd = $this->estimateMaxUsd(
            prompt: $prompt,
            template: $template . "\n\n" . mb_substr((string) ($post->content ?? ''), 0, 8000),
            maxOutputTokens: $maxOutput,
            inputPricePer1m: $settings->priceInputPer1m(),
            outputPricePer1m: $settings->priceOutputPer1m(),
            imageCostUsd: 0.0,
            budgetUsd: $settings->dailyBudgetUsd(),
        );

        $budgetDate = now()->toDateString();
        try {
            $reservedUsd = $limiter->reserveOrFail($estimatedMaxUsd, $budgetDate);
        } catch (BlogAiBudgetException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['prompt' => [$e->getMessage()]],
            ], 422);
        }

        $gen = BlogAiGeneration::query()->create([
            'user_id' => (int) $request->user()->id,
            'blog_post_id' => (int) $post->id,
            'blog_category_id' => (int) $post->blog_category_id,
            'action' => $action,
            'status' => 'queued',
            'budget_date' => $budgetDate,
            'prompt_user' => $prompt !== '' ? $prompt : '—',
            'prompt_template_snapshot' => $template,
            'model' => $model,
            'max_output_tokens' => $maxOutput,
            'reserved_usd' => $reservedUsd > 0 ? $reservedUsd : null,
        ]);

        dispatch(new RunBlogAiGenerationJob($gen->id));

        return response()->json([
            'generation_id' => $gen->id,
            'status' => $gen->status,
        ]);
    }

    public function status(BlogAiGeneration $generation, BlogAiLimiter $limiter): JsonResponse
    {
        $status = (string) $generation->status;

        // Watchdog: if job was killed by worker timeout, status may stay "running" forever.
        if (
            $status === 'running'
            && $generation->started_at
            && $generation->started_at->lt(now()->subMinutes(5))
        ) {
            $message = 'Задача генерации зависла или была остановлена по таймауту очереди. '
                . 'Проверьте timeout воркера очереди (kidscrm-queue.service --timeout) и права на запись в storage.';

            // Mark related running images as failed as well.
            BlogAiGeneratedImage::query()
                ->where('blog_ai_generation_id', $generation->id)
                ->whereIn('status', ['queued', 'running'])
                ->update([
                    'status' => 'failed',
                    'error_message' => $message,
                    'updated_at' => now(),
                ]);

            $spent = 0.0;
            if ($generation->cost_text_usd) {
                $spent += (float) $generation->cost_text_usd;
            }
            $spent += (float) BlogAiGeneratedImage::query()
                ->where('blog_ai_generation_id', $generation->id)
                ->where('status', 'succeeded')
                ->sum('cost_usd');

            $limiter->finalize(
                $generation->budget_date?->format('Y-m-d'),
                (float) ($generation->reserved_usd ?? 0),
                $spent
            );

            $generation->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $message,
                'progress' => 100,
                'phase' => 'done',
            ]);

            $generation->refresh();
            $status = (string) $generation->status;
        }

        $progress = (int) ($generation->progress ?? 0);
        if ($progress <= 0) {
            $progress = match ($status) {
                'queued' => 15,
                'running' => 55,
                'succeeded' => 100,
                'failed' => 100,
                default => 30,
            };
        }

        return response()->json([
            'id' => $generation->id,
            'status' => $status,
            'progress' => $progress,
            'phase' => $generation->phase,
            'error_message' => $generation->error_message,
            'created_at' => $generation->created_at?->toIso8601String(),
            'started_at' => $generation->started_at?->toIso8601String(),
            'finished_at' => $generation->finished_at?->toIso8601String(),
            'elapsed_seconds' => (int) max(
                0,
                ($generation->created_at?->diffInSeconds($generation->finished_at ?: now()) ?? 0)
            ),
            'blog_post_id' => $generation->blog_post_id,
            'edit_url' => $generation->blog_post_id ? route('admin.blog.posts.edit', $generation->blog_post_id) : null,
        ]);
    }

    private function estimateMaxUsd(
        string $prompt,
        string $template,
        int $maxOutputTokens,
        ?float $inputPricePer1m,
        ?float $outputPricePer1m,
        float $imageCostUsd,
        float $budgetUsd,
    ): float {
        if ($budgetUsd <= 0) {
            return 0.0;
        }
        $hasTextPricing = (($inputPricePer1m ?? 0) > 0) && (($outputPricePer1m ?? 0) > 0);

        $usd = 0.0;
        if ($hasTextPricing) {
            // Very rough estimation: ~1 token per 4 chars.
            $inputTokensEst = (int) ceil((mb_strlen($template . "\n\n" . $prompt, 'UTF-8') / 4));
            $outputTokensEst = max(1, $maxOutputTokens);

            $usd = ($inputTokensEst / 1_000_000) * (float) $inputPricePer1m
                + ($outputTokensEst / 1_000_000) * (float) $outputPricePer1m;
        }

        return (float) round($usd + $imageCostUsd, 4);
    }

    private function estimateImagesUsd(bool $wantCover, int $inlineCount, BlogAiImageSettings $imageSettings): float
    {
        $usd = 0.0;
        if ($wantCover) {
            $usd += max(0, $imageSettings->costCoverUsd());
        }
        if ($inlineCount > 0) {
            $usd += max(0, $imageSettings->costInlineUsd()) * $inlineCount;
        }
        return (float) round($usd, 4);
    }
}

