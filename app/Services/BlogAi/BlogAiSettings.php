<?php

namespace App\Services\BlogAi;

use App\Models\Setting;
use App\Services\BlogAi\DefaultPrompts;

class BlogAiSettings
{
    public function model(): string
    {
        return (string) $this->getText('blog.ai.model', 'gpt-5.1');
    }

    public function dailyBudgetUsd(): float
    {
        return (float) $this->getText('blog.ai.daily_budget_usd', '5');
    }

    public function priceInputPer1m(): ?float
    {
        $v = $this->getText('blog.ai.price_input_per_1m');
        return $v === null || $v === '' ? null : (float) $v;
    }

    public function priceOutputPer1m(): ?float
    {
        $v = $this->getText('blog.ai.price_output_per_1m');
        return $v === null || $v === '' ? null : (float) $v;
    }

    public function maxOutputTokens(): int
    {
        $v = (int) $this->getText('blog.ai.max_output_tokens', '4500');
        return $v > 0 ? $v : 4500;
    }

    public function promptTemplate(): string
    {
        $tpl = (string) $this->getText('blog.ai.prompt_template', DefaultPrompts::articleTemplate());
        return $tpl !== '' ? $tpl : DefaultPrompts::articleTemplate();
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
}

