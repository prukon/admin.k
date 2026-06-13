<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Enums\AuditEvent;
use App\Models\ContractTemplate;
use App\Models\MyLog;
use Illuminate\Support\Facades\Storage;

final class ContractTemplatesAuditLogsFeatureTest extends ContractsFeatureTestCase
{
    private function latestLog(AuditEvent $event): ?MyLog
    {
        return MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', $event->value)
            ->latest('id')
            ->first();
    }

    public function test_logs_data_returns_200_with_contracts_view(): void
    {
        $this->getJson(route('logs.data.contract-template', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->getJson(route('logs.data.contract-template', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertStatus(403);
    }

    public function test_index_renders_history_button_with_contracts_view(): void
    {
        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false);
    }

    public function test_store_writes_contract_template_created_log(): void
    {
        $this->post(route('contract-templates.store'), [
            'title' => 'Аудит создаётся',
            'docx'  => $this->fakeDocxUploadedFile(['parent_full_name']),
        ])->assertSessionHasNoErrors();

        $log = $this->latestLog(AuditEvent::ContractTemplateCreated);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::ContractTemplateCreated->level(), $log->level);
        $this->assertStringContainsString('Аудит создаётся', (string) $log->description);
        $this->assertStringContainsString('полей 1', (string) $log->description);
    }

    public function test_update_writes_contract_template_updated_log_with_field_diffs(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'До изменения']);

        $this->put(route('contract-templates.update', $template), [
            'title'  => 'После изменения',
            'fields' => [
                [
                    'key'            => 'parent_full_name',
                    'label'          => 'ФИО представителя',
                    'required'       => 0,
                    'prefill_source' => null,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $log = $this->latestLog(AuditEvent::ContractTemplateUpdated);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Название: До изменения → После изменения', (string) $log->description);
        $this->assertStringContainsString('parent_full_name, подпись:', (string) $log->description);
        $this->assertStringContainsString('Родитель: ФИО → ФИО представителя', (string) $log->description);
        $this->assertStringContainsString('parent_full_name, обязательность: Да → Нет', (string) $log->description);
    }

    public function test_update_without_changes_does_not_write_log(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'Без изменений']);

        $beforeCount = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::ContractTemplateUpdated->value)
            ->count();

        $this->put(route('contract-templates.update', $template), [
            'title' => 'Без изменений',
        ])->assertSessionHasNoErrors();

        $afterCount = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::ContractTemplateUpdated->value)
            ->count();

        $this->assertSame($beforeCount, $afterCount);
    }

    public function test_update_email_writes_contract_template_email_updated_log(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'Email audit']);

        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject'   => 'Новая тема письма',
            'email_body_html' => '<p>Новый текст письма</p>',
        ])->assertOk();

        $log = $this->latestLog(AuditEvent::ContractTemplateEmailUpdated);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Тема письма: Заполните договор → Новая тема письма', (string) $log->description);
        $this->assertStringContainsString('Тело письма:', (string) $log->description);
        $this->assertStringContainsString('Новый текст письма', (string) $log->description);
    }

    public function test_update_email_without_changes_does_not_write_log(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'Email без изменений']);

        $beforeCount = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::ContractTemplateEmailUpdated->value)
            ->count();

        $this->putJson(route('contract-templates.update-email', $template), [
            'email_subject'   => 'Заполните договор',
            'email_body_html' => '<p>Текст письма</p>',
        ])->assertOk();

        $afterCount = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', AuditEvent::ContractTemplateEmailUpdated->value)
            ->count();

        $this->assertSame($beforeCount, $afterCount);
    }

    public function test_update_with_new_docx_writes_version_and_field_changes(): void
    {
        Storage::fake();

        $template = $this->createContractTemplateWithVersion(['title' => 'Версия 1']);

        $this->put(route('contract-templates.update', $template), [
            'title' => 'Версия 1',
            'docx'  => $this->fakeDocxUploadedFile(['parent_full_name', 'parent_phone']),
        ])->assertSessionHasNoErrors();

        $log = $this->latestLog(AuditEvent::ContractTemplateUpdated);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Версия DOCX: 1 → 2', (string) $log->description);
        $this->assertStringContainsString('Поле parent_phone: добавлено', (string) $log->description);
    }

    public function test_logs_data_returns_written_contract_template_event_in_table(): void
    {
        $template = $this->createContractTemplateWithVersion(['title' => 'В таблице логов']);

        $this->put(route('contract-templates.update', $template), [
            'title' => 'После изменения',
        ])->assertSessionHasNoErrors();

        $descriptions = collect($this->getJson(route('logs.data.contract-template', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'В таблице логов → После изменения')),
            'Ожидалась запись contract_template.updated в logs-data.'
        );
    }
}
