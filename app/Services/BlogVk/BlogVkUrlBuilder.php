<?php

namespace App\Services\BlogVk;

use App\Models\BlogPost;

class BlogVkUrlBuilder
{
    public function __construct(
        private readonly BlogVkSettings $settings,
    ) {
    }

    public function build(BlogPost $post): string
    {
        $url = $post->publicUrl();
        $utm = $this->settings->utmParams();

        $query = http_build_query([
            'utm_source' => $utm['source'],
            'utm_medium' => $utm['medium'],
            'utm_campaign' => $utm['campaign'],
        ]);

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . $query;
    }
}
