<?php

namespace Tests\Feature\Crm\Blog\Concerns;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ConfiguresBlogVk
{
    protected function configureBlogVkEnabled(): void
    {
        config([
            'services.vk.enabled' => true,
            'services.vk.group_id' => '123456',
            'services.vk.access_token' => 'test-token',
            'services.vk.api_version' => '5.199',
            'services.vk.utm.source' => 'vk',
            'services.vk.utm.medium' => 'social',
            'services.vk.utm.campaign' => 'blog',
        ]);

        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.enabled', 'partner_id' => null],
            ['text' => '1']
        );

        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.ai_enabled', 'partner_id' => null],
            ['text' => '0']
        );
    }

    protected function configureBlogVkAiEnabled(): void
    {
        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.ai_enabled', 'partner_id' => null],
            ['text' => '1']
        );

        Setting::query()->updateOrCreate(
            ['name' => 'blog.ai.price_input_per_1m', 'partner_id' => null],
            ['text' => '2']
        );
        Setting::query()->updateOrCreate(
            ['name' => 'blog.ai.price_output_per_1m', 'partner_id' => null],
            ['text' => '8']
        );
        Setting::query()->updateOrCreate(
            ['name' => 'blog.ai.daily_budget_usd', 'partner_id' => null],
            ['text' => '10']
        );
    }

    protected function fakeOpenAiVkMessage(string $message): void
    {
        config(['services.openai.api_key' => 'test-openai-key']);

        $openAiResponse = Http::response([
            'output_text' => json_encode(['message' => $message], JSON_UNESCAPED_UNICODE),
            'usage' => ['input_tokens' => 100, 'output_tokens' => 80],
        ]);

        Http::fake([
            'https://api.openai.com/v1/responses' => $openAiResponse,
            'api.openai.com/v1/responses*' => $openAiResponse,
        ]);
    }

    protected function fakeVkApiWithOpenAiMessage(string $aiMessage): void
    {
        $openAiResponse = Http::response([
            'output_text' => json_encode(['message' => $aiMessage], JSON_UNESCAPED_UNICODE),
            'usage' => ['input_tokens' => 100, 'output_tokens' => 80],
        ]);

        Http::fake([
            'https://api.openai.com/v1/responses' => $openAiResponse,
            'api.openai.com/v1/responses*' => $openAiResponse,
            'api.vk.com/method/wall.post*' => Http::response([
                'response' => ['post_id' => 999],
            ]),
        ]);

        config(['services.openai.api_key' => 'test-openai-key']);
    }

    protected function configureBlogVkDisabledInAdmin(): void
    {
        Setting::query()->updateOrCreate(
            ['name' => 'blog.vk.enabled', 'partner_id' => null],
            ['text' => '0']
        );
    }

    protected function blogCategory(): BlogCategory
    {
        return BlogCategory::query()->create([
            'name' => 'Категория',
            'slug' => 'cat-' . Str::lower(Str::random(8)),
        ]);
    }

    protected function useTmpPublicDisk(): string
    {
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_public_disk_'
            . Str::uuid()->toString();

        if (!is_dir($root)) {
            @mkdir($root, 0777, true);
        }

        config([
            'filesystems.disks.public.driver' => 'local',
            'filesystems.disks.public.root' => $root,
        ]);
        app('filesystem')->forgetDisk('public');

        return $root;
    }

    protected function fakeVkApiSuccess(): void
    {
        Http::fake([
            'api.vk.com/method/wall.post*' => Http::response([
                'response' => ['post_id' => 999],
            ]),
        ]);
    }

    protected function configureBlogVkUserToken(): void
    {
        config(['services.vk.user_access_token' => 'test-user-token']);
    }

    protected function fakeVkApiWithPhotoUpload(): void
    {
        Http::fake([
            'api.vk.com/method/photos.getWallUploadServer*' => Http::response([
                'response' => [
                    'upload_url' => 'https://pu.vk.com/upload/',
                    'album_id' => 1,
                ],
            ]),
            'https://pu.vk.com/*' => Http::response([
                'server' => 123,
                'photo' => 'photo_payload',
                'hash' => 'hash_value',
            ]),
            'api.vk.com/method/photos.saveWallPhoto*' => Http::response([
                'response' => [[
                    'id' => 456,
                    'owner_id' => -123456,
                ]],
            ]),
            'api.vk.com/method/wall.post*' => Http::response([
                'response' => ['post_id' => 999],
            ]),
        ]);
    }

    protected function fakeVkApiWallPostError(): void
    {
        Http::fake([
            'api.vk.com/method/wall.post*' => Http::response([
                'error' => [
                    'error_code' => 15,
                    'error_msg' => 'Access denied',
                ],
            ]),
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function makePublishedBlogPostForVk(array $overrides = []): BlogPost
    {
        $category = $this->blogCategory();
        $this->useTmpPublicDisk();
        $coverPath = 'blog/covers/test-' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($coverPath, 'cover-bytes');

        return BlogPost::query()->create(array_merge([
            'blog_category_id' => $category->id,
            'title' => 'Тестовая статья',
            'slug' => 'test-' . Str::lower(Str::random(8)),
            'content' => '<p>' . str_repeat('Текст статьи. ', 20) . '</p>',
            'excerpt' => 'Краткое описание',
            'cover_image_path' => $coverPath,
            'is_published' => true,
            'published_at' => now()->subMinute(),
            'publish_to_vk' => true,
        ], $overrides));
    }
}
