<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\OutgoingEmailLog;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Тесты отчёта «Исходящие письма» (admin/reports/emails).
 *
 * Что покрываем:
 *  - доступ по permission can:reports.emails.view;
 *  - изоляция данных по partner_id (anti-leak);
 *  - корректность total/data/mailable-classes-search;
 *  - сохранение/чтение настроек колонок;
 *  - страница show доступна только для логов своего партнёра;
 *  - листенер LogOutgoingEmail (sending → sent) реально срабатывает
 *    и связывает запись с partner_id из X-Partner-Id или PartnerContext.
 */
class OutgoingEmailReportTest extends CrmTestCase
{
    /**
     * [P0] Все маршруты отчёта требуют can:reports.emails.view.
     */
    public function test_routes_require_permission(): void
    {
        // Заранее создаём существующий лог, чтобы show не вернул 404 из-за
        // route-model binding раньше срабатывания can-middleware.
        $existing = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 'permission-check',
        ]);

        $actor = $this->createUserWithoutPermission('reports.emails.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.emails.index'))->assertForbidden();
        $this->get(route('reports.emails.total'))->assertForbidden();
        $this->get(route('reports.emails.mailable.classes.search', ['q' => 'x']))->assertForbidden();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.emails.data', ['draw' => 1]))
            ->assertForbidden();

        $this->get('/admin/reports/emails/columns-settings')->assertForbidden();
        $this->postJson('/admin/reports/emails/columns-settings', ['columns' => ['id' => true]])->assertForbidden();
        $this->get(route('reports.emails.show', ['log' => $existing->id]))->assertForbidden();
    }

    /**
     * [P0] Index доступен суперадмину и устанавливает активный таб.
     */
    public function test_index_returns_ok_for_superadmin_and_sets_active_tab(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('reports.emails.index'))
            ->assertOk()
            ->assertViewIs('admin.report.index')
            ->assertViewHas('activeTab', 'emails');
    }

    /**
     * [P0] /admin/reports/emails/total — счётчики Всего/Отправлено/Ошибки
     * считаются только по current_partner (изоляция).
     */
    public function test_total_endpoint_returns_correct_counts_and_isolates_by_partner(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 's1',
        ]);
        OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 's2',
        ]);
        OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_FAILED,
            'subject' => 's3',
            'error_message' => 'boom',
        ]);
        OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENDING,
            'subject' => 's4',
        ]);
        // Чужого партнёра не должно быть в подсчёте:
        OutgoingEmailLog::create([
            'partner_id' => $this->foreignPartner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 'foreign',
        ]);

        $this->get(route('reports.emails.total'))
            ->assertOk()
            ->assertJson([
                'total_raw'  => 4,
                'sent_raw'   => 2,
                'failed_raw' => 1,
            ]);
    }

    /**
     * [P0] DataTables data: возвращает только записи current_partner.
     */
    public function test_data_endpoint_returns_only_current_partner_logs(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $own = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 'OwnSubject',
        ]);
        OutgoingEmailLog::create([
            'partner_id' => $this->foreignPartner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 'ForeignSubject',
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.emails.data', ['draw' => 1, 'start' => 0, 'length' => 50]))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $json);
        $ids = collect($json['data'])->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertCount(1, $ids);
    }

    /**
     * [P1] DataTables data: фильтр по статусу.
     */
    public function test_data_endpoint_filter_status(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $sent = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 'SentMail',
        ]);
        OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_FAILED,
            'subject' => 'FailedMail',
        ]);

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.emails.data', [
                'draw' => 1, 'start' => 0, 'length' => 50,
                'status' => ['sent'],
            ]))
            ->assertOk()
            ->json();

        $ids = collect($json['data'])->pluck('id')->all();
        $this->assertSame([$sent->id], array_values($ids));
    }

    /**
     * [P1] mailable-classes-search возвращает results[] и фильтрует по партнёру.
     */
    public function test_mailable_classes_search_returns_results_for_current_partner(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'mailable_class' => 'App\\Mail\\NewContactSubmission',
            'subject' => 's',
        ]);
        OutgoingEmailLog::create([
            'partner_id' => $this->foreignPartner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'notification_class' => 'App\\Notifications\\ForeignNotification',
            'subject' => 's',
        ]);

        $json = $this->get(route('reports.emails.mailable.classes.search', ['q' => 'NewContact']))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('results', $json);
        $ids = collect($json['results'])->pluck('id')->all();
        $this->assertContains('App\\Mail\\NewContactSubmission', $ids);
        $this->assertNotContains('App\\Notifications\\ForeignNotification', $ids);
    }

    /**
     * [P1] columns-settings (reports_outgoing_emails) сохраняются и читаются.
     */
    public function test_columns_settings_saved_and_loaded(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $payload = [
            'columns' => [
                'id' => true,
                'subject' => false,
                'status' => true,
            ],
        ];

        $this->postJson('/admin/reports/emails/columns-settings', $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_outgoing_emails')
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($payload['columns'], $setting->columns);

        $this->get('/admin/reports/emails/columns-settings')
            ->assertOk()
            ->assertExactJson($payload['columns']);
    }

    /**
     * [P0] show — открывается для собственного лога.
     */
    public function test_show_opens_for_own_log(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $log = OutgoingEmailLog::create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 'Hello',
        ]);

        $this->get(route('reports.emails.show', ['log' => $log->id]))
            ->assertOk()
            ->assertViewIs('admin.report.outgoing_email_show')
            ->assertViewHas('log');
    }

    /**
     * [P0] show — запрещает доступ к чужому логу.
     */
    public function test_show_forbids_foreign_log(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $foreignLog = OutgoingEmailLog::create([
            'partner_id' => $this->foreignPartner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 'ForeignOnly',
        ]);

        $this->get(route('reports.emails.show', ['log' => $foreignLog->id]))
            ->assertForbidden();
    }

    /**
     * [P0] Листенер LogOutgoingEmail срабатывает: на send создаётся запись 'sending',
     * после успеха становится 'sent'. partner_id берётся из X-Partner-Id.
     */
    public function test_listener_logs_outgoing_email_with_partner_header(): void
    {
        // Используем in-memory mail-транспорт, чтобы события Sending/Sent отстреливались.
        config(['mail.default' => 'array', 'queue.default' => 'sync']);

        $partnerId = $this->partner->id;

        Mail::raw('Hello body', function ($message) use ($partnerId) {
            $message->to('to@example.com')
                ->from('from@example.com', 'From Name')
                ->subject('Listener Test Subject');
            $message->getHeaders()->addTextHeader('X-Partner-Id', (string) $partnerId);
        });

        $log = OutgoingEmailLog::query()
            ->where('partner_id', $partnerId)
            ->where('subject', 'Listener Test Subject')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Запись OutgoingEmailLog должна быть создана листенером');
        $this->assertSame(OutgoingEmailLog::STATUS_SENT, $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertSame('from@example.com', $log->from_address);
        $this->assertSame('From Name', $log->from_name);
        $this->assertNotNull($log->to_summary);
    }

    /**
     * [P1] Если X-Partner-Id не задан, partner_id остаётся null
     * (PartnerContext в фоне может отдать что-то другое, но в данном тесте
     * мы шлём из «бесконтекстного» окружения — от имени sub-request без сессии).
     *
     * Здесь имитируем фон без current_partner: сбрасываем сессию.
     */
    public function test_listener_logs_with_null_partner_when_no_header_and_no_context(): void
    {
        config(['mail.default' => 'array', 'queue.default' => 'sync']);

        // Сбросим сессию и контекст партнёра, чтобы PartnerContext отдал null.
        // Имитация ситуации «письмо ушло из джоба, без X-Partner-Id».
        session()->forget('current_partner');

        // Сбросим closure cached partnerContext: пересоздадим инстанс на следующий вызов.
        app()->forgetInstance(\App\Services\PartnerContext::class);

        // Логаут, чтобы у Auth::user() не было partner_id.
        auth()->logout();

        Mail::raw('No-context body', function ($message) {
            $message->to('to2@example.com')
                ->from('from2@example.com')
                ->subject('No Context Subject');
        });

        $log = OutgoingEmailLog::query()
            ->where('subject', 'No Context Subject')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->partner_id);
        $this->assertSame(OutgoingEmailLog::STATUS_SENT, $log->status);
    }
}
