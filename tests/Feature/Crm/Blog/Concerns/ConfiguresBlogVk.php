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
            'api.vk.com/method/photos.getWallUploadServer*' => Http::response([
                'response' => ['upload_url' => 'https://upload.test/up'],
            ]),
            'upload.test/*' => Http::response([
                'server' => 1,
                'photo' => '{"test":1}',
                'hash' => 'abc',
            ]),
            'api.vk.com/method/photos.saveWallPhoto*' => Http::response([
                'response' => [[
                    'owner_id' => -123456,
                    'id' => 555,
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
            'api.vk.com/method/photos.getWallUploadServer*' => Http::response([
                'response' => ['upload_url' => 'https://upload.test/up'],
            ]),
            'upload.test/*' => Http::response([
                'server' => 1,
                'photo' => '{"test":1}',
                'hash' => 'abc',
            ]),
            'api.vk.com/method/photos.saveWallPhoto*' => Http::response([
                'response' => [[
                    'owner_id' => -123456,
                    'id' => 555,
                ]],
            ]),
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
