<?php

namespace Tests\Feature\Phone;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Phone\Concerns\InteractsWithPhoneInput;

/**
 * CRM-формы: сохранение случайного номера и отображение при повторной загрузке.
 */
final class PhoneFormCrmPersistenceFeatureTest extends CrmTestCase
{
    use InteractsWithPhoneInput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_admin_user_store_and_edit_reload_return_saved_phone(): void
    {
        $masked = $this->randomRuPhoneMasked();
        $role = Role::query()->where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $email = 'phone-persist-' . uniqid('', true) . '@example.test';

        $store = $this->postJson(route('admin.user.store'), [
            'name'       => 'Телефон',
            'lastname'   => 'Персист',
            'email'      => $email,
            'phone'      => $masked,
            'role_id'    => $role->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $user = User::findOrFail((int) $store->json('user.id'));
        $this->assertSame($this->expectedCanonicalPhone($masked), $user->phone);

        $edit = $this->getJson(route('admin.user.edit', $user), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();
        $this->assertSameNormalizedPhone($user->phone, $edit->json('user.phone'));
    }

    public function test_admin_user_update_and_edit_reload_return_updated_phone(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => Role::query()->where('name', 'user')->value('id'),
            'team_id'    => $team->id,
            'phone'      => '+79990001122',
        ]);

        $masked = $this->randomRuPhoneMasked();

        $this->patchJson(route('admin.user.update', $user), [
            'name'       => $user->name,
            'lastname'   => $user->lastname,
            'email'      => $user->email,
            'phone'      => $masked,
            'role_id'    => $user->role_id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $user->refresh();
        $this->assertSame($this->expectedCanonicalPhone($masked), $user->phone);

        $edit = $this->getJson(route('admin.user.edit', $user), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();
        $this->assertSameNormalizedPhone($user->phone, $edit->json('user.phone'));
    }

    public function test_trainer_store_and_show_reload_return_saved_phone(): void
    {
        $masked = $this->randomRuPhoneMasked();
        $email = 'trainer-phone-' . uniqid('', true) . '@example.test';

        $store = $this->postJson(route('admin.trainers.store'), [
            'lastname'   => 'Тренер',
            'name'       => 'Телефон',
            'email'      => $email,
            'phone'      => $masked,
            'is_enabled' => 1,
        ])->assertOk();

        $profileId = (int) $store->json('trainer.id');
        $show = $this->getJson(route('admin.trainers.show', $profileId))->assertOk();

        $this->assertSame($this->expectedCanonicalPhone($masked), $show->json('phone'));
    }

    public function test_trainer_update_and_show_reload_return_updated_phone(): void
    {
        $trainerUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => Role::query()->where('name', 'trainer')->value('id'),
            'phone'      => '+79990001122',
        ]);

        $profile = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $trainerUser->id,
        ]);

        $masked = $this->randomRuPhoneMasked();

        $this->putJson(route('admin.trainers.update', $profile), [
            'lastname'   => $trainerUser->lastname,
            'name'       => $trainerUser->name,
            'email'      => $trainerUser->email,
            'phone'      => $masked,
            'is_enabled' => 1,
        ])->assertOk();

        $show = $this->getJson(route('admin.trainers.show', $profile))->assertOk();
        $this->assertSame($this->expectedCanonicalPhone($masked), $show->json('phone'));
    }

    public function test_account_user_update_and_page_reload_show_saved_phone(): void
    {
        $masked = $this->randomRuPhoneMasked();

        $this->patchJson(route('account.user.update'), [
            'name'     => $this->user->name,
            'lastname' => $this->user->lastname,
            'phone'    => $masked,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])->assertOk();

        $this->user->refresh();
        $this->assertSame($this->expectedCanonicalPhone($masked), $this->user->phone);

        $html = (string) $this->get(route('account.user.edit'))->assertOk()->getContent();
        $this->assertHtmlContainsFormattedPhone($html, $this->user->phone);
    }

    public function test_account_partner_update_and_page_reload_show_saved_phone(): void
    {
        $masked = $this->randomRuPhoneMasked();

        $this->patchJson(route('admin.cur.partner.update', $this->partner), [
            'business_type' => 'company',
            'title'         => $this->partner->title,
            'email'         => $this->partner->email ?? ('org_' . Str::lower(Str::random(8)) . '@example.com'),
            'phone'         => $masked,
        ])->assertOk();

        $this->partner->refresh();
        $this->assertNotEmpty($this->partner->phone);

        $this->resetPartnerContextCache();

        $html = (string) $this->get(route('admin.cur.company.edit'))->assertOk()->getContent();
        $this->assertHtmlContainsFormattedPhone($html, $this->partner->phone);
    }

    public function test_student_parent_phone_update_and_edit_reload_return_saved_phone(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'phone'      => '+79990001122',
        ]);

        $studentRoleId = (int) Role::query()->where('name', 'user')->firstOrFail()->id;
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $studentRoleId,
            'team_id'    => $team->id,
            'parent_id'  => $parent->id,
        ]);

        $masked = $this->randomRuPhoneMasked();

        $this->patchJson(route('admin.user.update', $student), [
            'name'              => $student->name,
            'lastname'          => $student->lastname,
            'email'             => $student->email,
            'role_id'           => $studentRoleId,
            'team_id'           => $team->id,
            'is_enabled'        => 1,
            'parent_lastname'   => 'Родитель',
            'parent_firstname'  => 'Тест',
            'parent_middlename' => 'Тестович',
            'parent_phone'      => $masked,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $edit = $this->getJson(route('admin.user.edit', $student), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();
        $this->assertSameNormalizedPhone($masked, $edit->json('user.parent_phone'));
    }
}
