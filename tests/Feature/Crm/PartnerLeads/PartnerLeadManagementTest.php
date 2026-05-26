<?php

namespace Tests\Feature\Crm\PartnerLeads;

use App\Enums\PartnerLeadStatus;
use App\Models\PartnerLead;
use Illuminate\Support\Carbon;
use Tests\Feature\Crm\CrmTestCase;

class PartnerLeadManagementTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asSuperadmin();
    }

    public function test_update_partner_lead_successfully_updates_status_and_comment(): void
    {
        $partnerLead = PartnerLead::create([
            'name'   => 'Иван',
            'phone'  => '+7 999 123-45-67',
            'status' => 'new',
        ]);

        $payload = [
            'status'  => 'processing',
            'comment' => 'Перезвонить завтра',
        ];

        $response = $this->putJson(
            route('admin.partner-leads.update', ['partnerLead' => $partnerLead->id]),
            $payload
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Изменения сохранены.',
                'status'  => 'processing',
                'comment' => 'Перезвонить завтра',
            ]);

        $partnerLead->refresh();

        $this->assertEquals('processing', $partnerLead->status?->value);
        $this->assertEquals('Перезвонить завтра', $partnerLead->comment);
        $this->assertEquals(
            PartnerLeadStatus::label('processing'),
            $response->json('status_label')
        );
    }

    public function test_update_partner_lead_fails_with_invalid_status(): void
    {
        $partnerLead = PartnerLead::create([
            'name'   => 'Иван',
            'phone'  => '+7 999 123-45-67',
            'status' => 'new',
        ]);

        $response = $this->putJson(
            route('admin.partner-leads.update', ['partnerLead' => $partnerLead->id]),
            ['status' => 'foobar']
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);

        $partnerLead->refresh();
        $this->assertEquals('new', $partnerLead->status?->value);
    }

    public function test_destroy_partner_lead_soft_deletes_record(): void
    {
        $partnerLead = PartnerLead::create([
            'name'   => 'Иван',
            'phone'  => '+7 999 123-45-67',
            'status' => 'new',
        ]);

        $response = $this->deleteJson(
            route('admin.partner-leads.destroy', ['partnerLead' => $partnerLead->id])
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Заявка удалена.',
            ]);

        $partnerLead->refresh();

        $this->assertNotNull(
            $partnerLead->deleted_at,
            'Ожидали, что deleted_at будет заполнен после soft delete.'
        );

        $this->assertNull(
            PartnerLead::whereNull('deleted_at')->find($partnerLead->id),
            'Удалённый лид не должен попадать в активные записи.'
        );
    }

    public function test_partner_leads_datatable_returns_basic_structure_and_counts(): void
    {
        $deleted = PartnerLead::create([
            'name'       => 'Удалённый',
            'phone'      => '+7 900 000-00-00',
            'status'     => 'new',
            'created_at' => Carbon::now()->subDay(),
        ]);
        $deleted->delete();

        $first = PartnerLead::create([
            'name'       => 'Иван',
            'phone'      => '+7 999 123-45-67',
            'status'     => 'new',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $second = PartnerLead::create([
            'name'       => 'Пётр',
            'phone'      => '+7 999 765-43-21',
            'status'     => 'processing',
            'created_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->getJson(route('admin.partner-leads.data', [
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
                'stats' => ['total', 'new', 'processing'],
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

    public function test_partner_leads_datatable_filters_by_statuses(): void
    {
        PartnerLead::create([
            'name'   => 'Новый',
            'phone'  => '+7 900 111-11-11',
            'status' => 'new',
        ]);

        PartnerLead::create([
            'name'   => 'Обрабатывается 1',
            'phone'  => '+7 900 222-22-22',
            'status' => 'processing',
        ]);

        PartnerLead::create([
            'name'   => 'Обрабатывается 2',
            'phone'  => '+7 900 333-33-33',
            'status' => 'processing',
        ]);

        $response = $this->getJson(route('admin.partner-leads.data', [
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

    public function test_partner_leads_datatable_returns_stats_counts(): void
    {
        PartnerLead::create([
            'name'   => 'Stats new 1',
            'phone'  => '+7 900 111-11-11',
            'status' => 'new',
        ]);
        PartnerLead::create([
            'name'   => 'Stats new 2',
            'phone'  => '+7 900 222-22-22',
            'status' => 'new',
        ]);
        PartnerLead::create([
            'name'   => 'Stats processing',
            'phone'  => '+7 900 333-33-33',
            'status' => 'processing',
        ]);

        $response = $this->getJson(route('admin.partner-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $stats = $response->json('stats');
        $this->assertGreaterThanOrEqual(3, $stats['total']);
        $this->assertGreaterThanOrEqual(2, $stats['new']);
        $this->assertGreaterThanOrEqual(1, $stats['processing']);
    }
}
