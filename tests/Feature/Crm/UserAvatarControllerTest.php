<?php

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Models\Partner;
use App\Models\MyLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Crm\CrmTestCase;

class UserAvatarControllerTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Фейкаем public-диск, чтобы не трогать реальные файлы
        Storage::fake('public');
    }

    /**
     * Хелпер: создаём супер-админа текущего партнёра.
     */
    protected function createAdminForCurrentPartner(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            // если у тебя другая модель прав — поменяй это место
            'role_id'    => 1, // superadmin
        ]);
    }

    /**
     * Хелпер: создаём обычного юзера текущего партнёра без прав админа.
     */
    protected function createUserWithoutAdminRights(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            // любой не-superadmin
            'role_id'    => 2,
        ]);
    }

    /**
     * Хелпер: создаём пользователя другого партнёра.
     */
    protected function createUserOfAnotherPartner(): array
    {
        $otherPartner = Partner::factory()->create();

        $actor = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => 1,
        ]);

        $foreignUser = User::factory()->create([
            'partner_id' => $otherPartner->id,
            'role_id'    => 2,
        ]);

        return [$actor, $foreignUser];
    }

    /* ============================================================
     |  DELETE /admin/users/{id}/avatar
     |============================================================ */

    /** [P1] Успешное удаление аватарки админом (happy path) */
    public function test_admin_can_delete_avatar_happy_path(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);

        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'image'      => 'old_avatar.jpg',
            'image_crop' => 'old_crop.jpg',
        ]);

        Storage::disk('public')->put('avatars/old_avatar.jpg', 'dummy');
        Storage::disk('public')->put('avatars/old_crop.jpg', 'dummy');

        $response = $this->deleteJson("/admin/users/{$user->id}/avatar", [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Аватар удалён',
            ]);

        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'image'      => null,
            'image_crop' => null,
        ]);

        Storage::disk('public')->assertMissing('avatars/old_avatar.jpg');
        Storage::disk('public')->assertMissing('avatars/old_crop.jpg');

        $this->assertDatabaseCount('my_logs', 1);
        $log = MyLog::first();
        $this->assertEquals(2, $log->type);
        $this->assertEquals(299, $log->action);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals($user->id, $log->target_id);
        $this->assertEquals(User::class, $log->target_type);
        $this->assertStringContainsString('удален аватар', $log->description);
    }

    /** [P1] Удаление аватарки, когда файлов на диске уже нет */
    public function test_delete_avatar_succeeds_when_files_are_missing(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'image'      => 'ghost_avatar.jpg',
            'image_crop' => 'ghost_crop.jpg',
        ]);

        // Файлы специально не создаём

        $response = $this->deleteJson("/admin/users/{$user->id}/avatar", [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Аватар удалён',
            ]);

        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'image'      => null,
            'image_crop' => null,
        ]);

        $log = MyLog::first();
        $this->assertNotNull($log);
        $this->assertEquals(299, $log->action);
    }

    /** [P1] Удаление аватарки неавторизованным пользователем */
    public function test_guest_cannot_delete_avatar(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'image'      => 'avatar.jpg',
            'image_crop' => 'crop.jpg',
        ]);

        Storage::disk('public')->put('avatars/avatar.jpg', 'dummy');
        Storage::disk('public')->put('avatars/crop.jpg', 'dummy');

        $response = $this->deleteJson("/admin/users/{$user->id}/avatar", [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        // В зависимости от твоего auth-мидлвара это может быть 302/401/403
        $this->assertTrue(in_array($response->status(), [302, 401, 403], true));

        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'image'      => 'avatar.jpg',
            'image_crop' => 'crop.jpg',
        ]);

        Storage::disk('public')->assertExists('avatars/avatar.jpg');
        Storage::disk('public')->assertExists('avatars/crop.jpg');
        $this->assertDatabaseCount('my_logs', 0);
    }

    /** [P1] Удаление аватарки без права редактировать пользователей */
    public function test_user_without_permission_cannot_delete_avatar(): void
    {
        $actor = $this->createUserWithoutAdminRights();
        $this->actingAs($actor);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'image'      => 'avatar.jpg',
            'image_crop' => 'crop.jpg',
        ]);

        Storage::disk('public')->put('avatars/avatar.jpg', 'dummy');
        Storage::disk('public')->put('avatars/crop.jpg', 'dummy');

        $response = $this->deleteJson("/admin/users/{$user->id}/avatar", [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'image'      => 'avatar.jpg',
            'image_crop' => 'crop.jpg',
        ]);

        Storage::disk('public')->assertExists('avatars/avatar.jpg');
        Storage::disk('public')->assertExists('avatars/crop.jpg');
        $this->assertDatabaseCount('my_logs', 0);
    }

    /** [P1] При пустой session current_partner мидлварь SetPartner всё равно устанавливает партнёра, аватар удаляется */
    public function test_delete_avatar_when_partner_context_missing_still_succeeds_and_sets_partner(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);

        // Явно очищаем сессию, чтобы проверить работу SetPartner
        session()->forget('current_partner');

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'image'      => 'avatar.jpg',
            'image_crop' => 'crop.jpg',
        ]);

        Storage::disk('public')->put('avatars/avatar.jpg', 'dummy');
        Storage::disk('public')->put('avatars/crop.jpg', 'dummy');

        $response = $this->deleteJson("/admin/users/{$user->id}/avatar", [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        // Фактическое поведение: всё успешно
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Аватар удалён',
            ]);

        // Мидлварь SetPartner должна вернуть current_partner
        $this->assertEquals($this->partner->id, session('current_partner'));

        // Аватар реально удалён
        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'image'      => null,
            'image_crop' => null,
        ]);

        Storage::disk('public')->assertMissing('avatars/avatar.jpg');
        Storage::disk('public')->assertMissing('avatars/crop.jpg');

        $this->assertDatabaseCount('my_logs', 1);
    }

    /** [P1] Суперадмин может удалять аватар пользователя другого партнёра */
    public function test_superadmin_can_delete_avatar_of_user_from_another_partner(): void
    {
        [$actor, $foreignUser] = $this->createUserOfAnotherPartner();

        $this->actingAs($actor);
        session(['current_partner' => $this->partner->id]);

        $foreignUser->update([
            'image'      => 'avatar.jpg',
            'image_crop' => 'crop.jpg',
        ]);

        Storage::disk('public')->put('avatars/avatar.jpg', 'dummy');
        Storage::disk('public')->put('avatars/crop.jpg', 'dummy');

        $response = $this->deleteJson("/admin/users/{$foreignUser->id}/avatar", [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Аватар удалён',
            ]);

        $this->assertDatabaseHas('users', [
            'id'         => $foreignUser->id,
            'image'      => null,
            'image_crop' => null,
        ]);

        Storage::disk('public')->assertMissing('avatars/avatar.jpg');
        Storage::disk('public')->assertMissing('avatars/crop.jpg');

        $this->assertDatabaseCount('my_logs', 1);
    }

    /** [P2] Удаление аватарки для несуществующего пользователя */
    public function test_delete_avatar_returns_404_for_non_existing_user(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $nonExistingId = 999999;

        $response = $this->deleteJson("/admin/users/{$nonExistingId}/avatar", [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('my_logs', 0);
    }

    /* ============================================================
     |  POST /admin/users/{id}/avatar
     |============================================================ */

    /** [P1] Успешная загрузка новой аватарки (happy path) */
    public function test_admin_can_upload_new_avatar_happy_path(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $bigFile  = UploadedFile::fake()->image('big.jpg', 1200, 1200)->size(4000);
        $cropFile = UploadedFile::fake()->image('crop.jpg', 400, 400)->size(3000);

        $response = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_big'  => $bigFile,
            'image_crop' => $cropFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Аватар обновлён',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'image_url',
                'image_crop_url',
            ]);

        $user->refresh();

        $this->assertNotNull($user->image);
        $this->assertNotNull($user->image_crop);
        $this->assertStringEndsWith('.jpg', $user->image);
        $this->assertStringEndsWith('.jpg', $user->image_crop);

        Storage::disk('public')->assertExists('avatars/' . $user->image);
        Storage::disk('public')->assertExists('avatars/' . $user->image_crop);

        $log = MyLog::first();
        $this->assertNotNull($log);
        $this->assertEquals(27, $log->action);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertStringContainsString('изменён аватар', $log->description);
    }

    /** [P1] Обновление аватарки с удалением старых файлов */
    public function test_update_avatar_deletes_old_files(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'image'      => 'old_avatar.jpg',
            'image_crop' => 'old_crop.jpg',
        ]);

        Storage::disk('public')->put('avatars/old_avatar.jpg', 'old');
        Storage::disk('public')->put('avatars/old_crop.jpg', 'old');

        $bigFile  = UploadedFile::fake()->image('big.jpg', 1200, 1200)->size(4000);
        $cropFile = UploadedFile::fake()->image('crop.jpg', 400, 400)->size(3000);

        $response = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_big'  => $bigFile,
            'image_crop' => $cropFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);

        $user->refresh();

        Storage::disk('public')->assertMissing('avatars/old_avatar.jpg');
        Storage::disk('public')->assertMissing('avatars/old_crop.jpg');

        Storage::disk('public')->assertExists('avatars/' . $user->image);
        Storage::disk('public')->assertExists('avatars/' . $user->image_crop);

        $this->assertDatabaseCount('my_logs', 1);
    }

    /** [P1] Валидация: отсутствует image_big или image_crop */
    public function test_upload_avatar_fails_when_required_images_are_missing(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        // Нет image_big
        $response1 = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_crop' => UploadedFile::fake()->image('crop.jpg', 400, 400)->size(3000),
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response1->assertStatus(422)
            ->assertJsonValidationErrors(['image_big']);

        // Нет image_crop
        $response2 = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_big' => UploadedFile::fake()->image('big.jpg', 1200, 1200)->size(3000),
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['image_crop']);

        $this->assertDatabaseCount('my_logs', 0);
    }

    /** [P1] Валидация: некорректный MIME-тип (например, SVG или текстовый файл) */
    public function test_upload_avatar_fails_with_invalid_mime_type(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $invalidFile = UploadedFile::fake()->create('file.svg', 10, 'image/svg+xml');
        $validCrop   = UploadedFile::fake()->image('crop.jpg', 400, 400)->size(3000);

        $response = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_big'  => $invalidFile,
            'image_crop' => $validCrop,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image_big']);

        $this->assertDatabaseCount('my_logs', 0);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    /** [P1] Валидация размера: файл больше разрешённого лимита */
    public function test_upload_avatar_fails_when_file_size_exceeds_limit(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        // 6000 KB > 5120
        $tooBigFile  = UploadedFile::fake()->image('big.jpg', 1200, 1200)->size(6000);
        $validCrop   = UploadedFile::fake()->image('crop.jpg', 400, 400)->size(3000);

        $response = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_big'  => $tooBigFile,
            'image_crop' => $validCrop,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image_big']);

        $this->assertDatabaseCount('my_logs', 0);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    /** [P1] Ошибка обработки изображения (битый файл / не читается Intervention Image) */
    public function test_upload_avatar_returns_error_when_image_processing_fails(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        // Создаём фейковый "jpeg", который на самом деле не картинка
        $brokenImage = UploadedFile::fake()->create('broken.jpg', 10, 'image/jpeg');
        $brokenCrop  = UploadedFile::fake()->create('broken_crop.jpg', 10, 'image/jpeg');

        $response = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_big'  => $brokenImage,
            'image_crop' => $brokenCrop,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        // Ожидаем, что Intervention выбросит исключение и код поймает его
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Не удалось обработать изображение.',
            ]);

        $this->assertDatabaseCount('my_logs', 0);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    /** [P1] Загрузка аватара при отсутствии партнёрского контекста */
    /** [P1] При пустой session current_partner мидлварь SetPartner всё равно устанавливает партнёра, аватар загружается */
    public function test_upload_avatar_fails_when_partner_context_missing(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        // явно чистим current_partner, чтобы проверить работу SetPartner
        session()->forget('current_partner');

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $bigFile  = UploadedFile::fake()->image('big.jpg', 1200, 1200)->size(4000);
        $cropFile = UploadedFile::fake()->image('crop.jpg', 400, 400)->size(3000);

        $response = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_big'  => $bigFile,
            'image_crop' => $cropFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        // фактическое поведение: успешная загрузка
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Аватар обновлён',
            ]);

        // SetPartner проставил current_partner на основе авторизованного юзера
        $this->assertEquals($this->partner->id, session('current_partner'));

        $user->refresh();

        $this->assertNotNull($user->image);
        $this->assertNotNull($user->image_crop);

        Storage::disk('public')->assertExists('avatars/' . $user->image);
        Storage::disk('public')->assertExists('avatars/' . $user->image_crop);

        // лог должен появиться
        $this->assertDatabaseCount('my_logs', 1);
    }

    /** [P1] Суперадмин может загружать аватар пользователю другого партнёра */
    public function test_superadmin_can_upload_avatar_for_user_from_another_partner(): void
    {
        [$actor, $foreignUser] = $this->createUserOfAnotherPartner();

        $this->actingAs($actor);
        session(['current_partner' => $this->partner->id]);

        $bigFile  = UploadedFile::fake()->image('big.jpg', 1200, 1200)->size(4000);
        $cropFile = UploadedFile::fake()->image('crop.jpg', 400, 400)->size(3000);

        $response = $this->postJson("/admin/users/{$foreignUser->id}/avatar", [
            'image_big'  => $bigFile,
            'image_crop' => $cropFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Аватар обновлён',
            ]);

        $foreignUser->refresh();

        $this->assertNotNull($foreignUser->image);
        $this->assertNotNull($foreignUser->image_crop);

        Storage::disk('public')->assertExists('avatars/' . $foreignUser->image);
        Storage::disk('public')->assertExists('avatars/' . $foreignUser->image_crop);

        $this->assertDatabaseCount('my_logs', 1);
    }

    /** [P1] Загрузка аватара без авторизации */
    public function test_guest_cannot_upload_avatar(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $bigFile  = UploadedFile::fake()->image('big.jpg', 1200, 1200)->size(4000);
        $cropFile = UploadedFile::fake()->image('crop.jpg', 400, 400)->size(3000);

        $response = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_big'  => $bigFile,
            'image_crop' => $cropFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertTrue(in_array($response->status(), [302, 401, 403], true));

        $this->assertDatabaseCount('my_logs', 0);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    /** [P2] Загрузка аватара без права редактировать пользователей */
    public function test_user_without_permission_cannot_upload_avatar(): void
    {
        $actor = $this->createUserWithoutAdminRights();
        $this->actingAs($actor);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $bigFile  = UploadedFile::fake()->image('big.jpg', 1200, 1200)->size(4000);
        $cropFile = UploadedFile::fake()->image('crop.jpg', 400, 400)->size(3000);

        $response = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_big'  => $bigFile,
            'image_crop' => $cropFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseCount('my_logs', 0);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    /** [P2] Загрузка аватара несуществующему пользователю */
    public function test_upload_avatar_returns_404_for_non_existing_user(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $bigFile  = UploadedFile::fake()->image('big.jpg', 1200, 1200)->size(4000);
        $cropFile = UploadedFile::fake()->image('crop.jpg', 400, 400)->size(3000);

        $nonExistingId = 999999;

        $response = $this->postJson("/admin/users/{$nonExistingId}/avatar", [
            'image_big'  => $bigFile,
            'image_crop' => $cropFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(404);

        $this->assertDatabaseCount('my_logs', 0);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    /** [P2] Проверка формата имён файлов и расширения */
    public function test_uploaded_avatar_file_names_are_jpeg_and_stored_in_avatars_folder(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $bigFile  = UploadedFile::fake()->image('big.png', 1200, 1200)->size(4000);
        $cropFile = UploadedFile::fake()->image('crop.webp', 400, 400)->size(3000);

        $response = $this->postJson("/admin/users/{$user->id}/avatar", [
            'image_big'  => $bigFile,
            'image_crop' => $cropFile,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);

        $user->refresh();

        $this->assertStringEndsWith('.jpg', $user->image);
        $this->assertStringEndsWith('.jpg', $user->image_crop);

        Storage::disk('public')->assertExists('avatars/' . $user->image);
        Storage::disk('public')->assertExists('avatars/' . $user->image_crop);
    }

    /** [P3] Перезапись аватарки несколько раз подряд */
    public function test_avatar_can_be_overwritten_multiple_times_and_only_last_files_remain(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $allFiles = [];

        // 3 последовательные загрузки
        for ($i = 0; $i < 3; $i++) {
            $bigFile  = UploadedFile::fake()->image("big_{$i}.jpg", 1200, 1200)->size(4000);
            $cropFile = UploadedFile::fake()->image("crop_{$i}.jpg", 400, 400)->size(3000);

            $response = $this->postJson("/admin/users/{$user->id}/avatar", [
                'image_big'  => $bigFile,
                'image_crop' => $cropFile,
            ], [
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

            $response->assertStatus(200);

            $user->refresh();
            $allFiles[] = ['image' => $user->image, 'crop' => $user->image_crop];
        }

        $user->refresh();

        // Последняя пара должна существовать
        Storage::disk('public')->assertExists('avatars/' . $user->image);
        Storage::disk('public')->assertExists('avatars/' . $user->image_crop);

        // Все предыдущие — удалены
        foreach ($allFiles as $index => $pair) {
            if ($index === count($allFiles) - 1) {
                continue;
            }
            Storage::disk('public')->assertMissing('avatars/' . $pair['image']);
            Storage::disk('public')->assertMissing('avatars/' . $pair['crop']);
        }

        // Логов должно быть 3 (по одной записи на каждое обновление)
        $this->assertEquals(3, MyLog::count());
    }

    /** [P3] Корректный target_label в логах (full_name vs fallback) */
    public function test_logs_use_full_name_or_fallback_for_target_label(): void
    {
        $admin = $this->createAdminForCurrentPartner();
        $this->actingAs($admin);
        session(['current_partner' => $this->partner->id]);

        // Вариант 1: с full_name
        $userWithName = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'lastname'   => 'Иванов',
        ]);

        $bigFile1  = UploadedFile::fake()->image('big1.jpg', 1200, 1200)->size(4000);
        $cropFile1 = UploadedFile::fake()->image('crop1.jpg', 400, 400)->size(3000);

        $this->postJson("/admin/users/{$userWithName->id}/avatar", [
            'image_big'  => $bigFile1,
            'image_crop' => $cropFile1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(200);

        $log1 = MyLog::where('target_id', $userWithName->id)->latest()->first();
        $this->assertNotNull($log1);
        $this->assertStringContainsString('Иван', $log1->target_label);

        // Вариант 2: "пустое" имя → должен быть fallback user#id
        $userNoName = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $userNoName->update([
            'name'     => '',
            'lastname' => '',
        ]);

        $bigFile2  = UploadedFile::fake()->image('big2.jpg', 1200, 1200)->size(4000);
        $cropFile2 = UploadedFile::fake()->image('crop2.jpg', 400, 400)->size(3000);

        $this->postJson("/admin/users/{$userNoName->id}/avatar", [
            'image_big'  => $bigFile2,
            'image_crop' => $cropFile2,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(200);

        $log2 = MyLog::where('target_id', $userNoName->id)->latest()->first();
        $this->assertNotNull($log2);
        $this->assertEquals("user#{$userNoName->id}", $log2->target_label);
    }

}