<?php

namespace App\Services\BlogVk;

use App\Models\BlogPost;
use App\Services\BlogAi\BlogAiLimiter;
use App\Services\BlogAi\BlogAiSettings;
use App\Services\BlogAi\Exceptions\BlogAiBudgetException;
use App\Services\BlogAi\Exceptions\OpenAiRequestException;
use App\Services\BlogAi\OpenAiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlogVkAiMessageGenerator
{
    private const MAX_MESSAGE_CHARS = 400;

    private const ESTIMATED_MAX_USD = 0.03;

    public function __construct(
        private readonly BlogVkSettings $vkSettings,
        private readonly BlogAiSettings $aiSettings,
        private readonly OpenAiClient $openAiClient,
        private readonly BlogAiLimiter $limiter,
    ) {
    }

    /**
     * Генерирует уникальный анонс для VK. При ошибке возвращает null (fallback на шаблон).
     */
    public function generate(BlogPost $post): ?string
    {
        if (!$this->vkSettings->aiEnabled()) {
            return null;
        }

        $reservedUsd = 0.0;
        $spentUsd = 0.0;
        $budgetDate = now()->toDateString();

        try {
            $reservedUsd = $this->limiter->reserveOrFail(self::ESTIMATED_MAX_USD, $budgetDate);
        } catch (BlogAiBudgetException $e) {
            Log::channel('queue')->warning('VK AI message: budget limit', [
                'blog_post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        try {
            $payload = $this->buildPayload($post);
            $resp = $this->openAiClient->responses($payload);

            $usageIn = (int) (data_get($resp, 'usage.input_tokens') ?? 0);
            $usageOut = (int) (data_get($resp, 'usage.output_tokens') ?? 0);
            $spentUsd = $this->calcCostUsd($usageIn, $usageOut);

            $outputText = $this->extractOutputText($resp);
            $message = $this->parseMessage($outputText);

            if ($message === null || $message === '') {
                Log::channel('queue')->warning('VK AI message: empty result', [
                    'blog_post_id' => $post->id,
                ]);

                return null;
            }

            Log::channel('queue')->info('VK AI message generated', [
                'blog_post_id' => $post->id,
                'length' => mb_strlen($message, 'UTF-8'),
            ]);

            return $this->truncate($message);
        } catch (OpenAiRequestException|\Throwable $e) {
            Log::channel('queue')->warning('VK AI message: generation failed', [
                'blog_post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            if ($reservedUsd > 0) {
                $this->limiter->finalize($budgetDate, $reservedUsd, $spentUsd);
            }
        }
    }

    private function buildPayload(BlogPost $post): array
    {
        $plain = trim(strip_tags((string) $post->content));
        $articleExcerpt = trim(strip_tags((string) ($post->excerpt ?? '')));
        if ($articleExcerpt === '' && $plain !== '') {
            $articleExcerpt = Str::limit($plain, 1200, '…');
        }

        $userBlock = "Заголовок статьи:\n" . $post->title . "\n\n";
        if ($articleExcerpt !== '') {
            $userBlock .= "Краткое описание / начало статьи:\n" . $articleExcerpt . "\n\n";
        }
        if ($post->category?->name) {
            $userBlock .= "Категория: " . $post->category->name . "\n\n";
        }
        $userBlock .= "Напиши анонс для поста в группе ВКонтакте (без URL — ссылку добавим отдельно).";

        $system = $this->vkSettings->aiPromptTemplate();

        return [
            'model' => $this->aiSettings->model(),
            'input' => $system . "\n\n---\n\n" . $userBlock,
            'max_output_tokens' => min(600, $this->aiSettings->maxOutputTokens()),
            'text' => [
                'format' => [
                    'type' => 'json_object',
                ],
            ],
        ];
    }

    private function parseMessage(string $outputText): ?string
    {
        $text = trim($outputText);
        if ($text === '') {
            return null;
        }

        if (str_starts_with($text, '{')) {
            $data = json_decode($text, true);
            if (is_array($data)) {
                $msg = trim((string) ($data['message'] ?? $data['vk_message'] ?? $data['text'] ?? ''));

                return $msg !== '' ? $msg : null;
            }
        }

        return $text;
    }

    private function extractOutputText(array $resp): string
    {
        $ot = (string) (data_get($resp, 'output_text') ?? '');
        if ($ot !== '') {
            return $ot;
        }

        $output = data_get($resp, 'output', []);
        if (!is_array($output)) {
            return '';
        }

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

        return '';
    }

    private function calcCostUsd(int $inputTokens, int $outputTokens): float
    {
        $inPrice = $this->aiSettings->priceInputPer1m();
        $outPrice = $this->aiSettings->priceOutputPer1m();
        if (($inPrice ?? 0) <= 0 || ($outPrice ?? 0) <= 0) {
            return 0.0;
        }

        $usd = ($inputTokens / 1_000_000) * (float) $inPrice
            + ($outputTokens / 1_000_000) * (float) $outPrice;

        return (float) round($usd, 4);
    }

    private function truncate(string $message): string
    {
        return mb_substr(trim($message), 0, self::MAX_MESSAGE_CHARS, 'UTF-8');
    }
}
