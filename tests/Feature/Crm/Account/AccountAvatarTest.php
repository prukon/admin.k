<?php

namespace Tests\Feature\Crm\Account;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

class AccountAvatarTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Storage::fake пишет в storage/framework/testing. На этом стенде у тестового пользователя может не быть прав.
        $tmpStorage = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kidscrm-storage' . DIRECTORY_SEPARATOR . (string) Str::uuid();
        File::ensureDirectoryExists($tmpStorage);
        $this->app->useStoragePath($tmpStorage);

        Storage::fake('public');
    }

    /**
     * (P1) store: без обязательных файлов -> 422
     */
    public function test_store_avatar_requires_files(): void
    {
        $resp = $this->withSession(['current_partner' => $this->partner->id])
            ->postJson('/profile/avatar', []);

        $resp->assertStatus(422);
    }

    /**
     * (P1) store: успешная загрузка сохраняет 2 файла, пишет поля в БД, отдаёт json.
     */
    public function test_store_avatar_succeeds_and_saves_files_and_db_fields(): void
    {
        $big = UploadedFile::fake()->image('big.jpg', 2000, 1200);   // будет jpeg
        $crop = UploadedFile::fake()->image('crop.jpg', 600, 600);

        $resp = $this->withSession(['current_partner' => $this->partner->id])
            ->postJson('/profile/avatar', [
                'image_big' => $big,
                'image_crop' => $crop,
            ]);

        $resp->assertOk();
        $resp->assertJsonStructure([
            'message',
            'image_url',
            'image_crop_url',
            'image',
            'image_crop',
        ]);

        $this->user->refresh();

        $this->assertNotNull($this->user->image);
        $this->assertNotNull($this->user->image_crop);

        Storage::disk('public')->assertExists('avatars/'.$this->user->image);
        Storage::disk('public')->assertExists('avatars/'.$this->user->image_crop);

        $this->assertDatabaseHas('my_logs', [
            'type' => 2,
            'action' => 28,
            'target_id' => $this->user->id,
            'target_type' => User::class,
        ]);
    }

    /**
     * (P1) store: при наличии старых файлов они удаляются.
     */
    public function test_store_avatar_deletes_old_files_when_replacing(): void
    {
        // вручную положим "старые" файлы и проставим в БД
        $oldBig = 'old_big.jpg';
        $oldCrop = 'old_crop.jpg';

        Storage::disk('public')->put('avatars/'.$oldBig, 'old');
        Storage::disk('public')->put('avatars/'.$oldCrop, 'old');

        $this->user->image = $oldBig;
        $this->user->image_crop = $oldCrop;
        $this->user->save();

        $big = UploadedFile::fake()->image('big.jpg', 1800, 1000);
        $crop = UploadedFile::fake()->image('crop.jpg', 600, 600);

        $resp = $this->withSession(['current_partner' => $this->partner->id])
            ->postJson('/profile/avatar', [
                'image_big' => $big,
                'image_crop' => $crop,
            ]);

        $resp->assertOk();

        Storage::disk('public')->assertMissing('avatars/'.$oldBig);
        Storage::disk('public')->assertMissing('avatars/'.$oldCrop);

        $this->user->refresh();
        Storage::disk('public')->assertExists('avatars/'.$this->user->image);
        Storage::disk('public')->assertExists('avatars/'.$this->user->image_crop);
    }

    /**
     * (P1) destroy: удаляет файлы, чистит поля, пишет MyLog.
     */
    public function test_destroy_avatar_deletes_files_and_clears_db_fields(): void
    {
        // подготовим файлы
        $bigName = 'big.jpg';
        $cropName = 'crop.jpg';
        Storage::disk('public')->put('avatars/'.$bigName, 'x');
        Storage::disk('public')->put('avatars/'.$cropName, 'x');

        $this->user->image = $bigName;
        $this->user->image_crop = $cropName;
        $this->user->save();

        $resp = $this->withSession(['current_partner' => $this->partner->id])
            ->deleteJson('/profile/avatar');

        $resp->assertOk();
        $resp->assertJsonPath('message', 'Фото удалено');

        Storage::disk('public')->assertMissing('avatars/'.$bigName);
        Storage::disk('public')->assertMissing('avatars/'.$cropName);

        $this->user->refresh();
        $this->assertNull($this->user->image);
        $this->assertNull($this->user->image_crop);

        $this->assertDatabaseHas('my_logs', [
            'type' => 2,
            'action' => 29,
            'target_id' => $this->user->id,
            'target_type' => User::class,
        ]);
    }
}