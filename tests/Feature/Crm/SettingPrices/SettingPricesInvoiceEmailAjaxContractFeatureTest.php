<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Mail\PaymentNotificationMail;
use App\Models\ParentProfile;
use App\Models\Partner;
use App\Models\PaymentNotificationRule;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Models\UserPricePublicPayLink;
use Illuminate\Support\Facades\Mail;

/**
 * JSON-контракт модалки «Отправить счёт на email».
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SettingPricesInvoiceEmailAjaxContractFeatureTest extends SettingPricesInvoiceEmailTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
    }

    public function test_preview_shows_charge_facts_and_not_an_empty_ok(): void
    {
        Mail::fake();
        $this->seedTbank();
        $row = $this->charge();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($row));

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('can_send', true)
            ->assertJsonPath('facts.package', 'Фикс октябрь')
            ->assertJsonPath('facts.email', 'student-invoice@example.com')
            ->assertJsonPath('facts.student_name', 'Тестов Иван')
            ->assertJsonPath('facts.team', 'Младшая группа')
            ->assertJsonPath('facts.parent_name', 'нет, в письме обращение на ученика')
            ->assertJsonPath('facts.pay_url', 'кнопка «Оплатить через СБП»')
            ->assertJsonStructure([
                'ok',
                'can_send',
                'errors',
                'facts' => ['package', 'amount', 'month', 'team', 'student_name', 'parent_name', 'email', 'pay_url'],
                'subject',
                'email_html',
            ]);

        $this->assertNotSame('', trim($response->getContent()));
        $this->assertNotSame('{}', trim($response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertStringContainsString('Оплатить через СБП', (string) $response->json('email_html'));
        $this->assertStringContainsString('/pm/', (string) $response->json('email_html'));
        $this->assertStringStartsWith('Оплата за', (string) $response->json('subject'));
        Mail::assertNothingSent();
    }

    public function test_send_delivers_the_default_letter_to_the_parent(): void
    {
        Mail::fake();
        $this->seedTbank();
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Петрова',
            'firstname' => 'Мария',
            'middlename' => 'Ивановна',
            'email' => 'parent-invoice@example.com',
        ]);
        $this->student->update(['parent_id' => $parent->id]);
        $row = $this->charge();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($row))
            ->assertOk()
            ->assertJsonPath('can_send', true)
            ->assertJsonPath('facts.email', 'parent-invoice@example.com')
            ->assertJsonPath('facts.parent_name', 'Петрова Мария Ивановна')
            ->assertJsonPath('facts.pay_url', 'кнопка «Оплатить через СБП»');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.send'), $this->payload($row))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Счёт отправлен на parent-invoice@example.com.');

        $link = UserPricePublicPayLink::query()->where('users_price_id', $row->id)->first();
        $this->assertNotNull($link);
        Mail::assertSent(PaymentNotificationMail::class, function (PaymentNotificationMail $mail) use ($link) {
            return $mail->hasTo('parent-invoice@example.com')
                && ! $mail->hasTo('student-invoice@example.com')
                && str_contains($mail->bodyHtml, 'Петрова Мария Ивановна')
                && str_contains($mail->bodyHtml, 'Оплатить через СБП')
                && str_contains($mail->bodyHtml, '/pm/'.$link->short_code)
                && str_contains($mail->emailSubject, 'Оплата за');
        });
    }

    public function test_missing_parent_email_falls_back_to_the_student(): void
    {
        Mail::fake();
        $this->seedTbank();
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Петрова',
            'firstname' => 'Мария',
            'email' => null,
        ]);
        $this->student->update(['parent_id' => $parent->id]);
        $row = $this->charge();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($row))
            ->assertOk()
            ->assertJsonPath('can_send', true)
            ->assertJsonPath('facts.email', 'student-invoice@example.com')
            ->assertJsonPath('facts.parent_name', 'Петрова Мария');
    }

    public function test_club_notification_rule_does_not_replace_the_default_letter(): void
    {
        $this->seedTbank();
        PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Чужой текст',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'СЕКРЕТ {{month_year}}',
            'body_html_template' => '<p>Текст правила клуба</p>',
        ]);
        $row = $this->charge();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($row))
            ->assertOk()
            ->assertJsonPath('can_send', true);

        $this->assertStringStartsWith('Оплата за', (string) $response->json('subject'));
        $this->assertStringNotContainsString('СЕКРЕТ', (string) $response->json('subject'));
        $this->assertStringNotContainsString('Текст правила клуба', (string) $response->json('email_html'));
        $this->assertStringContainsString('Оплатить через СБП', (string) $response->json('email_html'));
    }

    public function test_blank_form_returns_422_under_each_field(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'team_id', 'new_month'])
            ->assertJsonPath('errors.user_id.0', 'Укажите ученика.')
            ->assertJsonPath('errors.team_id.0', 'Укажите группу.')
            ->assertJsonPath('errors.new_month.0', 'Укажите месяц начисления.');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.send'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'new_month' => 'октябрь',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.new_month.0', 'Месяц начисления указан неверно.');
    }

    public function test_paid_zero_and_manual_marks_block_send_under_amount(): void
    {
        Mail::fake();
        $paid = $this->charge(['is_paid' => 1]);
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($paid))
            ->assertOk()
            ->assertJsonPath('can_send', false)
            ->assertJsonPath('errors.amount.0', 'Этот период уже оплачен.')
            ->assertJsonPath('facts.pay_url', 'недоступна');
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.send'), $this->payload($paid))
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Этот период уже оплачен.');
        Mail::assertNothingSent();

        $manual = $this->charge([
            'new_month' => '2026-11-01',
            'is_paid' => 0,
            'is_manual_paid' => 1,
        ]);
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.send'), $this->payload($manual))
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Этот период уже оплачен.');

        $acquiring = $this->charge([
            'new_month' => '2026-12-01',
            'is_paid' => 1,
            'is_manual_paid' => 0,
        ]);
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($acquiring))
            ->assertOk()
            ->assertJsonPath('can_send', false)
            ->assertJsonPath('errors.amount.0', 'Этот период уже оплачен через платёжную систему.');

        $zero = $this->charge([
            'new_month' => '2026-09-01',
            'price_cents' => 0,
            'is_paid' => 0,
        ]);
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($zero))
            ->assertOk()
            ->assertJsonPath('can_send', false)
            ->assertJsonPath('errors.amount.0', 'Сумма к оплате должна быть больше нуля.');
    }

    public function test_missing_email_is_returned_under_the_email_field(): void
    {
        $this->student->update(['email' => 'not-an-email']);
        $row = $this->charge();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($row))
            ->assertOk()
            ->assertJsonPath('can_send', false)
            ->assertJsonPath('errors.email.0', 'Нет корректного email родителя или ученика.')
            ->assertJsonPath('facts.email', '—');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.send'), $this->payload($row))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Нет корректного email родителя или ученика.');
    }

    public function test_charge_without_package_returns_error_under_package(): void
    {
        $row = $this->charge(['lesson_package_id' => null]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($row))
            ->assertOk()
            ->assertJsonPath('can_send', false)
            ->assertJsonPath('facts.package', 'не выбран')
            ->assertJsonPath('errors.package.0', 'У начисления не выбран абонемент.');
    }

    public function test_without_tbank_pay_link_is_unavailable_and_send_is_rejected(): void
    {
        Mail::fake();
        $row = $this->charge();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($row))
            ->assertOk()
            ->assertJsonPath('can_send', false)
            ->assertJsonPath('facts.pay_url', 'недоступна')
            ->assertJsonPath('errors.pay_url.0', 'Оплата через СБП недоступна: у школы не подключён T‑Bank.');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.send'), $this->payload($row))
            ->assertStatus(422)
            ->assertJsonPath('errors.pay_url.0', 'Оплата через СБП недоступна: у школы не подключён T‑Bank.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('user_price_public_pay_links', 0);
    }

    public function test_amount_below_sbp_minimum_blocks_the_pay_link(): void
    {
        Mail::fake();
        $this->seedTbank();
        $row = $this->charge(['price_cents' => 500]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($row))
            ->assertOk()
            ->assertJsonPath('can_send', false)
            ->assertJsonPath('facts.pay_url', 'недоступна')
            ->assertJsonPath('errors.pay_url.0', 'Сумма вне диапазона оплаты через СБП: от 10 до 1 000 000 ₽.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('user_price_public_pay_links', 0);
    }

    public function test_missing_email_still_shows_the_pay_button_when_the_link_exists(): void
    {
        $this->seedTbank();
        $this->student->update(['email' => 'not-an-email']);
        $row = $this->charge();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), $this->payload($row))
            ->assertOk()
            ->assertJsonPath('can_send', false)
            ->assertJsonPath('errors.email.0', 'Нет корректного email родителя или ученика.')
            ->assertJsonPath('facts.pay_url', 'кнопка «Оплатить через СБП»');
    }

    public function test_another_schools_charge_is_not_found(): void
    {
        $this->asAdmin();
        $other = Partner::factory()->create();
        $team = Team::factory()->create([
            'partner_id' => $other->id,
            'deleted_at' => null,
        ]);
        $student = User::factory()->create([
            'partner_id' => $other->id,
            'is_enabled' => true,
        ]);
        UserPrice::query()->create([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'new_month' => self::MONTH_DATE,
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->package->id,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.preview'), [
                'user_id' => $student->id,
                'team_id' => $team->id,
                'new_month' => self::MONTH_DATE,
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Начисление не найдено.');

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.invoice-email.send'), [
                'user_id' => $student->id,
                'team_id' => $team->id,
                'new_month' => self::MONTH_DATE,
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Начисление не найдено.');
    }

    public function test_json_accept_without_ajax_header_still_returns_json_not_empty_200(): void
    {
        $this->seedTbank();
        $row = $this->charge();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post(route('setting-prices.invoice-email.preview'), $this->payload($row));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode());
        $response->assertOk()->assertJsonPath('can_send', true);
        $this->assertNotSame('', trim($response->getContent()));
        $this->assertNotSame('{}', trim($response->getContent()));
    }
}
