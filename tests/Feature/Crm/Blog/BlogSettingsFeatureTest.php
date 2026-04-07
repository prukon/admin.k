<?php

namespace Tests\Feature\Crm\Blog;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class BlogSettingsFeatureTest extends BlogAdminFeatureTestCase
{
    public function test_edit_page_opens_with_200(): void
    {
        $this->get(route('admin.blog.settings.edit'))
            ->assertOk()
            ->assertViewIs('admin.blog.settings.edit')
            ->assertSee('SEO-настройки', escape: false);
    }

    public function test_update_validates_and_returns_back_with_errors(): void
    {
        $res = $this->from(route('admin.blog.settings.edit'))
            ->post(route('admin.blog.settings.update'), [
                'ai_daily_budget_usd' => -1,
                'ai_max_output_tokens' => 10,
                'ai_prompt_template' => 'short',
                'ai_images_cover_target_size' => 'bad',
            ]);

        $res->assertStatus(302);
        $res->assertSessionHasErrors([
            'ai_daily_budget_usd',
            'ai_max_output_tokens',
            'ai_prompt_template',
            'ai_images_cover_target_size',
        ]);
    }

    public function test_update_uploads_default_og_image_and_replaces_old_file(): void
    {
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_public_disk_'
            . Str::uuid()->toString();

        if (!is_dir($root)) {
            @mkdir($root, 0777, true);
        }
        @chmod($root, 0777);

        config([
            'filesystems.disks.public.driver' => 'local',
            'filesystems.disks.public.root' => $root,
        ]);
        app('filesystem')->forgetDisk('public');

        // old file
        Storage::disk('public')->put('blog/seo/old.webp', 'old');
        Setting::query()->updateOrCreate(
            ['name' => 'blog.default.og_image_path', 'partner_id' => null],
            ['text' => 'blog/seo/old.webp']
        );

        $file = UploadedFile::fake()->image('og.webp', 1200, 630)->size(500);

        $res = $this->from(route('admin.blog.settings.edit'))
            ->post(route('admin.blog.settings.update'), [
                'index_meta_title' => 'Блог',
                'index_meta_description' => 'Desc',
                'post_title_template' => '{title} — Блог',
                'ai_prompt_template' => str_repeat('A', 60),
                'default_og_image' => $file,
            ]);

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();
        $res->assertSessionHas('success', 'Настройки блога сохранены.');

        $newPath = (string) Setting::query()
            ->where('name', 'blog.default.og_image_path')
            ->whereNull('partner_id')
            ->value('text');

        $this->assertNotSame('', $newPath);
        $this->assertNotSame('blog/seo/old.webp', $newPath);

        $this->assertFalse(Storage::disk('public')->exists('blog/seo/old.webp'));
        $this->assertTrue(Storage::disk('public')->exists($newPath));
    }
}

