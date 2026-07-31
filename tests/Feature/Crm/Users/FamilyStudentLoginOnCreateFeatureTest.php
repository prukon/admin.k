<?php

namespace Tests\Feature\Crm\Users;

use App\Mail\ClientSiblingAddedMail;
use App\Mail\ClientWelcomeCredentialsMail;
use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Логин семьи при create ученика: первый ребёнок получает users.email = parent_email;
 * второй с тем же parent_id — без нового логина + sibling-письмо.
 *
 * @see /docs/documentation/admin-users.html §5
 * @see /docs/documentation/parents-and-family-cabinet.html
 */
final class FamilyStudentLoginOnCreateFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantPermission($this->user, 'schoolLeads.view');
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    private function trainerRoleId(): int
    {
        return (int) Role::query()->where('name', 'trainer')->value('id');
    }

    public function test_lead_first_child_assigns_parent_email_as_login_and_sends_credentials(): void
    {
        Mail::fake();

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Петя',
            'phone'                 => '+7 900 100-10-10',
            'parent_email'          => 'ivan-family@example.com',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'             => 'Петя',
            'lastname'         => 'Иванов',
            'role_id'          => $this->studentRoleId(),
            'is_enabled'       => 1,
            'school_lead_id'   => $lead->id,
            'parent_email'     => 'ivan-family@example.com',
            'parent_lastname'  => 'Иванов',
            'parent_firstname' => 'Иван',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('welcome_email_sent', true);

        $user = User::findOrFail((int) $response->json('user.id'));
        $this->assertSame('ivan-family@example.com', $user->email);
        $this->assertNotNull($user->password);
        $this->assertNotNull($user->parent_id);

        Mail::assertSent(ClientWelcomeCredentialsMail::class, function (ClientWelcomeCredentialsMail $mail) {
            return $mail->hasTo('ivan-family@example.com');
        });
        Mail::assertNotSent(ClientSiblingAddedMail::class);
    }

    public function test_lead_second_child_same_parent_creates_without_login_and_sends_sibling_mail(): void
    {
        Mail::fake();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванов',
            'firstname'  => 'Иван',
            'email'      => 'ivan-sibling@example.com',
        ]);

        $petya = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Петя',
            'lastname'   => 'Иванов',
            'email'      => 'ivan-sibling@example.com',
            'parent_id'  => $parent->id,
            'password'   => Hash::make('ExistingPass12'),
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Коля',
            'phone'                 => '+7 900 100-20-20',
            'parent_email'          => 'ivan-sibling@example.com',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'             => 'Коля',
            'lastname'         => 'Иванов',
            'role_id'          => $this->studentRoleId(),
            'is_enabled'       => 1,
            'school_lead_id'   => $lead->id,
            'parent_id'        => $parent->id,
            'parent_email'     => 'ivan-sibling@example.com',
            'parent_lastname'  => 'Иванов',
            'parent_firstname' => 'Иван',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('welcome_email_sent', true)
            ->assertJsonFragment([
                'message' => 'Клиент создан. Уведомление о доступе через семейный кабинет отправлено на ivan-sibling@example.com.',
            ]);

        $kolya = User::findOrFail((int) $response->json('user.id'));
        $this->assertNull($kolya->email);
        $this->assertNull($kolya->password);
        $this->assertSame($parent->id, $kolya->parent_id);
        $this->assertSame($kolya->id, (int) $lead->fresh()->user_id);

        Mail::assertSent(ClientSiblingAddedMail::class, function (ClientSiblingAddedMail $mail) use ($kolya, $petya) {
            return $mail->hasTo('ivan-sibling@example.com')
                && $mail->student->is($kolya)
                && $mail->familyLoginUser->is($petya);
        });
        Mail::assertNotSent(ClientWelcomeCredentialsMail::class);
    }

    public function test_lead_parent_email_occupied_by_other_family_returns_422(): void
    {
        $otherParent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email'      => 'taken-other@example.com',
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'taken-other@example.com',
            'parent_id'  => $otherParent->id,
        ]);

        $newParent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email'      => 'taken-other@example.com',
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Чужой',
            'phone'                 => '+7 900 100-30-30',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'           => 'Чужой',
            'lastname'       => 'Ребёнок',
            'role_id'        => $this->studentRoleId(),
            'is_enabled'     => 1,
            'school_lead_id' => $lead->id,
            'parent_id'      => $newParent->id,
            'parent_email'   => 'taken-other@example.com',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email'])
            ->assertJsonFragment([
                'message' => 'Этот адрес электронной почты уже зарегистрирован.',
            ]);

        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_lead_parent_email_occupied_by_trainer_returns_422(): void
    {
        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->trainerRoleId(),
            'email'      => 'trainer-login@example.com',
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Конфликт',
            'phone'                 => '+7 900 100-40-40',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'             => 'Конфликт',
            'lastname'         => 'Тренерский',
            'role_id'          => $this->studentRoleId(),
            'is_enabled'       => 1,
            'school_lead_id'   => $lead->id,
            'parent_email'     => 'trainer-login@example.com',
            'parent_lastname'  => 'Родитель',
            'parent_firstname' => 'Новый',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_email']);
    }

    public function test_manual_create_second_child_with_welcome_uses_sibling_flow(): void
    {
        Mail::fake();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Сидоров',
            'firstname'  => 'Пётр',
            'email'      => 'sidorov-family@example.com',
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'sidorov-family@example.com',
            'parent_id'  => $parent->id,
            'password'   => Hash::make('ExistingPass12'),
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'               => 'Вася',
            'lastname'           => 'Сидоров',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
            'parent_id'          => $parent->id,
            'parent_email'       => 'sidorov-family@example.com',
            'parent_lastname'    => 'Сидоров',
            'parent_firstname'   => 'Пётр',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()
            ->assertJsonPath('welcome_email_sent', true);

        $vasya = User::findOrFail((int) $response->json('user.id'));
        $this->assertNull($vasya->email);
        $this->assertSame($parent->id, $vasya->parent_id);

        Mail::assertSent(ClientSiblingAddedMail::class);
        Mail::assertNotSent(ClientWelcomeCredentialsMail::class);
    }

    public function test_lead_updates_parent_card_email_when_directory_parent_selected(): void
    {
        Mail::fake();

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванов',
            'firstname'  => 'Иван',
            'email'      => 'old-parent@example.com',
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'old-parent@example.com',
            'parent_id'  => $parent->id,
            'password'   => Hash::make('ExistingPass12'),
        ]);

        // Sibling flow всё ещё ищет holder по НОВОМУ parent_email — для обновления карточки
        // без конфликта логина меняем email на свободный и создаём первого ребёнка другой семьи.
        // Здесь: второй ребёнок с тем же логином семьи, плюс правка email карточки на тот же адрес.
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Коля',
            'phone'                 => '+7 900 100-50-50',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->postJson(route('admin.user.store'), [
            'name'             => 'Коля',
            'lastname'         => 'Иванов',
            'role_id'          => $this->studentRoleId(),
            'is_enabled'       => 1,
            'school_lead_id'   => $lead->id,
            'parent_id'        => $parent->id,
            'parent_email'     => 'old-parent@example.com',
            'parent_lastname'  => 'Иванов-Новый',
            'parent_firstname' => 'Иван',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $parent->refresh();
        $this->assertSame('Иванов-Новый', $parent->lastname);
        $this->assertSame('old-parent@example.com', $parent->email);
    }
}
