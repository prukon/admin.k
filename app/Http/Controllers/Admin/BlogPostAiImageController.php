<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\Admin\RegenerateAiBlogImageRequest;
use App\Jobs\BlogAi\RunBlogAiImageRegenerationJob;
use App\Models\BlogAiGeneratedImage;
use App\Models\BlogAiGeneration;
use App\Models\BlogPost;
use App\Services\BlogAi\BlogAiImageSettings;
use App\Services\BlogAi\BlogAiLimiter;
use App\Services\BlogAi\Exceptions\BlogAiBudgetException;
use Illuminate\Http\JsonResponse;

class BlogPostAiImageController extends Controller
{
    public function regenerate(
        BlogPost $post,
        BlogAiGeneratedImage $image,
        RegenerateAiBlogImageRequest $request,
        BlogAiImageSettings $imageSettings,
        BlogAiLimiter $limiter,
    ): JsonResponse {
        if ((int) $image->blog_post_id !== (int) $post->id) {
            abort(404);
        }

        if (!$imageSettings->enabled()) {
            return response()->json([
                'message' => 'Генерация изображений отключена в настройках блога.',
                'errors' => ['prompt_extra' => ['Генерация изображений отключена в настройках блога.']],
            ], 422);
        }

        $cost = $image->kind === 'cover'
            ? (float) $imageSettings->costCoverUsd()
            : (float) $imageSettings->costInlineUsd();

        $budgetDate = now()->toDateString();

        $reservedUsd = 0.0;
        try {
            if ($cost > 0) {
                $reservedUsd = $limiter->reserveOrFail($cost, $budgetDate);
            }
        } catch (BlogAiBudgetException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['prompt_extra' => [$e->getMessage()]],
            ], 422);
        }

        $gen = BlogAiGeneration::query()->create([
            'user_id' => (int) $request->user()->id,
            'blog_post_id' => (int) $post->id,
            'blog_ai_generated_image_id' => (int) $image->id,
            'blog_category_id' => (int) $post->blog_category_id,
            'action' => 'image_regen',
            'status' => 'queued',
            'budget_date' => $budgetDate,
            'progress' => 0,
            'phase' => $image->kind === 'cover' ? 'cover' : 'inline',
            'prompt_user' => (string) ($request->validated('prompt_extra') ?? ''),
            'prompt_template_snapshot' => '',
            'model' => $imageSettings->model(),
            'max_output_tokens' => 0,
            'reserved_usd' => $reservedUsd > 0 ? $reservedUsd : null,
        ]);

        dispatch(new RunBlogAiImageRegenerationJob($gen->id));

        return response()->json([
            'generation_id' => $gen->id,
            'status' => $gen->status,
        ]);
    }
}

