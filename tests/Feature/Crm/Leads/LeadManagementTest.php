<?php

namespace Tests\Feature\Crm\Leads;

use App\Enums\ContactSubmissionStatus;
use App\Models\ContactSubmission;
use Illuminate\Support\Carbon;
use Tests\Feature\Crm\CrmTestCase;

class LeadManagementTest extends CrmTestCase
{
    /**
     * Успешное обновление статуса и комментария лида.
     */
    public function test_update_lead_successfully_updates_status_and_comment(): void
    {
        $submission = ContactSubmission::create([
            'name'   => 'Иван',
            'phone'  => '+7 999 123-45-67',
            'status' => 'new',
        ]);

        $payload = [
            'status'  => 'processing',
            'comment' => 'Перезвонить завтра',
        ];

        $response = $this->putJson(
            route('admin.leads.update', ['submission' => $submission->id]),
            $payload
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Изменения сохранены.',
                'status'  => 'processing',
                'comment' => 'Перезвонить завтра',
            ]);

        $submission->refresh();

        $this->assertEquals('processing', $submission->status?->value);
        $this->assertEquals('Перезвонить завтра', $submission->comment);
        $this->assertEquals(
            ContactSubmissionStatus::label('processing'),
            $response->json('status_label')
        );
    }

    /**
     * Ошибка при передаче недопустимого статуса.
     */
    public function test_update_lead_fails_with_invalid_status(): void
    {
        $submission = ContactSubmission::create([
            'name'   => 'Иван',
            'phone'  => '+7 999 123-45-67',
            'status' => 'new',
        ]);

        $response = $this->putJson(
            route('admin.leads.update', ['submission' => $submission->id]),
            ['status' => 'foobar']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);

        $submission->refresh();
        $this->assertEquals('new', $submission->status?->value);
    }

    /**
     * Soft delete лида через контроллер.
     */
    public function test_destroy_lead_soft_deletes_submission(): void
    {
        $submission = ContactSubmission::create([
            'name'   => 'Иван',
            'phone'  => '+7 999 123-45-67',
            'status' => 'new',
        ]);

        $response = $this->deleteJson(
            route('admin.leads.destroy', ['submission' => $submission->id])
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Заявка удалена.',
            ]);

        $submission->refresh();

        $this->assertNotNull(
            $submission->deleted_at,
            'Ожидали, что deleted_at будет заполнен после soft delete.'
        );

        $this->assertNull(
            ContactSubmission::whereNull('deleted_at')->find($submission->id),
            'Удалённый лид не должен попадать в активные записи.'
        );
    }

    /**
     * Базовый ответ DataTables без фильтров.
     */
    public function test_leads_datatable_returns_basic_structure_and_counts(): void
    {
        // Удалённый лид
        $deleted = ContactSubmission::create([
            'name'       => 'Удалённый',
            'phone'      => '+7 900 000-00-00',
            'status'     => 'new',
            'created_at' => Carbon::now()->subDay(),
        ]);
        $deleted->delete();

        // Два активных
        $first = ContactSubmission::create([
            'name'       => 'Иван',
            'phone'      => '+7 999 123-45-67',
            'status'     => 'new',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $second = ContactSubmission::create([
            'name'       => 'Пётр',
            'phone'      => '+7 999 765-43-21',
            'status'     => 'processing',
            'created_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->getJson(route('admin.leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                        'website',
                        'message',
                        'status',
                        'status_label',
                        'comment',
                        'created_at',
                    ],
                ],
            ]);

        $this->assertEquals(2, $response->json('recordsTotal'));
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertCount(2, $response->json('data'));

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($first->id, $ids);
        $this->assertContains($second->id, $ids);
    }

    /**
     * DataTables: фильтрация по статусу.
     */
    public function test_leads_datatable_filters_by_statuses(): void
    {
        ContactSubmission::create([
            'name'   => 'Новый',
            'phone'  => '+7 900 111-11-11',
            'status' => 'new',
        ]);

        ContactSubmission::create([
            'name'   => 'Обрабатывается 1',
            'phone'  => '+7 900 222-22-22',
            'status' => 'processing',
        ]);

        ContactSubmission::create([
            'name'   => 'Обрабатывается 2',
            'phone'  => '+7 900 333-33-33',
            'status' => 'processing',
        ]);

        $response = $this->getJson(route('admin.leads.data', [
            'draw'     => 1,
            'start'    => 0,
            'length'   => 10,
            'statuses' => ['processing'],
        ]));

        $response->assertStatus(200);

        $this->assertEquals(3, $response->json('recordsTotal'));
        $this->assertEquals(2, $response->json('recordsFiltered'));
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $row) {
            $this->assertSame('processing', $row['status']);
        }
    }
}