<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\PaymentSystem;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserPrice;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка семейного переключателя и консоли: selected, @can, payload активного ученика.
 *
 * @see resources/views/includes/family_student_switcher.blade.php
 * @see resources/views/dashboard.blade.php
 */
final class FamilyStudentPaymentMarkupFeatureTest extends CrmTestCase
{
    use FamilyStudentPaymentFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->createSiblingStudents();
    }

    public function test_cabinet_switcher_selects_login_account_before_switch(): void
    {
        $this->actingAs($this->brother1);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Просмотр данных ученика', $html);
        $this->assertStringContainsString('id="family-active-student"', $html);
        $this->assertStringContainsString('name="student_user_id"', $html);
        $this->assertStringContainsString((string) ($this->brother1->full_name ?: $this->brother1->name), $html);
        $this->assertStringContainsString((string) ($this->brother2->full_name ?: $this->brother2->name), $html);
        $this->assertMatchesRegularExpression(
            '/<option value="'.$this->brother1->id.'"[^>]*selected/i',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="'.$this->brother2->id.'"[^>]*selected/i',
            $html
        );
    }

    public function test_cabinet_after_switch_selects_brother2_and_embeds_his_prices_only(): void
    {
        $team1 = $this->makeTeam('Группа брата 1');
        $team2 = $this->makeTeam('Группа брата 2');
        $this->attachStudent($this->brother1, $team1);
        $this->attachStudent($this->brother2, $team2);
        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, '2026-09-01', 100000, false, (int) $team1->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 250000, false, (int) $team2->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="'.$this->brother2->id.'"[^>]*selected/i',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="'.$this->brother1->id.'"[^>]*selected/i',
            $html
        );
        $this->assertStringContainsString('var dashboardStudentId = '.$this->brother2->id, $html);
        $this->assertStringContainsString('paymentUrl: \''.route('payment').'\'', $html);
        $this->assertStringContainsString('"user_id":'.$this->brother2->id, $html);
        $this->assertStringNotContainsString('"user_id":'.$this->brother1->id, $html);
        $this->assertStringContainsString('"team_id":'.$team2->id, $html);
        $this->assertStringNotContainsString('"team_id":'.$team1->id, $html);
    }

    public function test_custom_payment_form_after_switch_posts_sibling_id_not_login_account(): void
    {
        $this->grantPermission($this->brother1, 'setPrices.customPayments.view');
        $team = $this->makeTeam('Общая');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);

        $own = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->brother1->id,
            'team_id' => $team->id,
            'date_start' => '2026-09-01',
            'date_end' => '2026-09-30',
            'amount_cents' => 11100,
            'note' => 'Счёт Пети',
            'is_paid' => 0,
        ]);
        $sibling = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->brother2->id,
            'team_id' => $team->id,
            'date_start' => '2026-09-01',
            'date_end' => '2026-09-30',
            'amount_cents' => 22200,
            'note' => 'Счёт Васи',
            'is_paid' => 0,
        ]);

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('name="payment_kind" value="custom_payment"', $html);
        $this->assertStringContainsString('name="custom_payment_id" value="'.$sibling->id.'"', $html);
        $this->assertStringNotContainsString('name="custom_payment_id" value="'.$own->id.'"', $html);
        $this->assertStringContainsString('Счёт Васи', $html);
        $this->assertStringNotContainsString('Счёт Пети', $html);
        $this->assertStringContainsString('action="'.route('payment').'"', $html);
        $this->assertStringContainsString('Оплатить', $html);
    }

    public function test_without_paying_classes_custom_pay_button_is_disabled_not_a_form(): void
    {
        $this->grantPermission($this->brother1, 'setPrices.customPayments.view');
        DB::table('permission_role')
            ->where('role_id', $this->brother1->role_id)
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $this->permissionId('paying.classes'))
            ->delete();

        $team = $this->makeTeam('Общая');
        $this->attachStudent($this->brother2, $team);
        UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->brother2->id,
            'team_id' => $team->id,
            'date_start' => '2026-09-01',
            'date_end' => '2026-09-30',
            'amount_cents' => 22200,
            'note' => 'Счёт без права',
            'is_paid' => 0,
        ]);

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Счёт без права', $html);
        $this->assertStringNotContainsString('name="custom_payment_id"', $html);
        $this->assertStringContainsString('disabled', $html);
    }

    public function test_lonely_student_does_not_see_family_switcher(): void
    {
        $lonely = $this->createUserWithRole('user');
        $this->actingAs($lonely);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('family-active-student', $html);
        $this->assertStringNotContainsString('Просмотр данных ученика', $html);
    }

    public function test_payment_page_after_switch_shows_sibling_team_title(): void
    {
        $team = $this->makeTeam('Группа Васи');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 250000, false, (int) $team->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment'), $this->monthlyPayload((int) $team->id, '2026-09-01'))
            ->assertOk()
            ->assertSee('Группа Васи', false)
            ->assertSee('2 500', false);
    }

    public function test_payment_page_after_switch_shows_sibling_as_payer_not_login_account(): void
    {
        $this->enableRobokassaForActor($this->brother1);

        $team = $this->makeTeam('Группа Васи');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 250000, false, (int) $team->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $response = $this->post(route('payment'), $this->monthlyPayload((int) $team->id, '2026-09-01'))
            ->assertOk();
        $html = $response->getContent();

        $this->assertVitrinePayerIs($html, $this->brother2, $this->brother1);
        $this->assertVitrineHiddenPayerFields($html, $this->brother2, $this->brother1);
        $response->assertViewHas('payerStudent', function ($student): bool {
            return $student instanceof User && (int) $student->id === (int) $this->brother2->id;
        });
    }

    public function test_payment_page_without_switch_shows_login_student_as_payer(): void
    {
        $this->enableRobokassaForActor($this->brother1);

        $team = $this->makeTeam('Группа Пети');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);
        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, '2026-09-01', 100000, false, (int) $team->id)->create();

        $this->actingAs($this->brother1);

        $response = $this->post(route('payment'), $this->monthlyPayload((int) $team->id, '2026-09-01'))
            ->assertOk();
        $html = $response->getContent();

        $this->assertVitrinePayerIs($html, $this->brother1, $this->brother2);
        $this->assertVitrineHiddenPayerFields($html, $this->brother1, $this->brother2);
        $response->assertViewHas('payerStudent', function ($student): bool {
            return $student instanceof User && (int) $student->id === (int) $this->brother1->id;
        });
    }

    private function enableRobokassaForActor(User $actor): void
    {
        $this->grantPermission($actor, 'payment.method.robokassa');
        PaymentSystem::factory()->robokassa()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
    }

    private function assertVitrinePayerIs(string $html, User $expected, User $notExpected): void
    {
        $this->assertMatchesRegularExpression(
            '/<div class="summary-item-label">Плательщик<\/div>\s*<div class="summary-item-value">\s*'
            .preg_quote((string) $expected->name, '/')
            .'\s*<\/div>/u',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<div class="summary-item-label">Плательщик<\/div>\s*<div class="summary-item-value">\s*'
            .preg_quote((string) $notExpected->name, '/')
            .'\s*<\/div>/u',
            $html
        );
    }

    private function assertVitrineHiddenPayerFields(string $html, User $expected, User $notExpected): void
    {
        $this->assertMatchesRegularExpression(
            '/name="userName"\s+value="'.preg_quote((string) $expected->name, '/').'"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="userName"\s+value="'.preg_quote((string) $notExpected->name, '/').'"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/name="userId"\s+value="'.$expected->id.'"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="userId"\s+value="'.$notExpected->id.'"/',
            $html
        );
    }
}
