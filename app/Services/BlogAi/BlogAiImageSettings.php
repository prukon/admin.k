<?php

namespace App\Services\BlogAi;

use App\Models\Setting;

class BlogAiImageSettings
{
    public function defaultCoverCount(): int
    {
        $v = (int) $this->getText('blog.ai.images.default_cover_count', '1');
        return $v <= 0 ? 0 : 1; // currently support 0/1 in UI
    }

    public function defaultInlineCount(): int
    {
        $v = (int) $this->getText('blog.ai.images.default_inline_count', '2');
        return max(0, min(3, $v));
    }

    public function enabled(): bool
    {
        $v = (string) $this->getText('blog.ai.images.enabled', '1');
        return $v !== '0';
    }

    public function model(): string
    {
        return (string) $this->getText('blog.ai.images.model', 'gpt-image-1');
    }

    /** auto|low|medium|high */
    public function quality(): string
    {
        return (string) $this->getText('blog.ai.images.quality', 'medium');
    }

    /** auto|transparent|opaque */
    public function background(): string
    {
        return (string) $this->getText('blog.ai.images.background', 'opaque');
    }

    /** webp|png|jpeg */
    public function outputFormat(): string
    {
        return (string) $this->getText('blog.ai.images.output_format', 'webp');
    }

    public function outputCompression(): int
    {
        $v = (int) $this->getText('blog.ai.images.output_compression', '85');
        return max(0, min(100, $v));
    }

    public function coverTargetSize(): string
    {
        return (string) $this->getText('blog.ai.images.cover_target_size', '1200x630');
    }

    public function inlineTargetSize43(): string
    {
        return (string) $this->getText('blog.ai.images.inline_target_size_43', '960x720');
    }

    public function inlineTargetSizeSquare(): string
    {
        return (string) $this->getText('blog.ai.images.inline_target_size_square', '768x768');
    }

    public function costCoverUsd(): float
    {
        return (float) $this->getText('blog.ai.images.cost_cover_usd', '0.02');
    }

    public function costInlineUsd(): float
    {
        return (float) $this->getText('blog.ai.images.cost_inline_usd', '0.01');
    }

    public function style(): string
    {
        return (string) $this->getText('blog.ai.images.style', '');
    }

    public function palette(): string
    {
        return (string) $this->getText('blog.ai.images.palette', '');
    }

    public function rules(): string
    {
        return (string) $this->getText('blog.ai.images.rules', '');
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

