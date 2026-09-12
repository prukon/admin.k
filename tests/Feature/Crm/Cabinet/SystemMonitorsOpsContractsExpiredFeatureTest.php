<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Contract;
use App\Models\Partner;
use App\Models\User;
use App\Support\OpsMonitor;

/**
 * Пульт «Договоры»: fill_expired (форма кабинета) и sms_expired (Подпислон), без окна 24 ч, все школы.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsContractsExpiredFeatureTest extends SystemMonitorsTestCase
{
    public function test_fill_expired_template_awaiting_is_counted_and_still_fillable_is_not(): void
    {
        $this->asSuperadmin();
        $expiredAt = now()->subHour();
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Третьяк',
            'name' => 'Натан',
        ]);
        $expired = $this->makeContract($student, $this->partner, [
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at' => $expiredAt,
        ]);
        $this->makeContract($student, $this->partner, [
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('contracts.fill_expired_count', 1)
            ->assertJsonPath('contracts.sms_expired_count', 0)
            ->assertJsonPath('contracts.fill_expired.0.name', 'Третьяк Натан')
            ->assertJsonPath('contracts.fill_expired.0.school', (string) $this->partner->title)
            ->assertJsonPath('contracts.fill_expired.0.at', $expiredAt->getTimestamp());

        $this->assertSame([], $response->json('contracts.sms_expired'));
        $this->assertArrayNotHasKey('email', $response->json('contracts.fill_expired.0'));
        $this->assertStringNotContainsString((string) $student->email, (string) $response->getContent());
        $this->assertNotNull($expired->id);
    }

    public function test_pdf_mode_and_other_statuses_are_not_fill_expired(): void
    {
        $this->asSuperadmin();
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Иванов',
            'name' => 'Пётр',
        ]);
        $past = now()->subDay();
        $this->makeContract($student, $this->partner, [
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at' => $past,
        ]);
        foreach ([
            Contract::STATUS_DRAFT,
            Contract::STATUS_SENT,
            Contract::STATUS_SIGNED,
            Contract::STATUS_REVOKED,
            Contract::STATUS_FAILED,
        ] as $status) {
            $this->makeContract($student, $this->partner, [
                'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
                'status' => $status,
                'fill_expires_at' => $past,
            ]);
        }
        $this->makeContract($student, $this->partner, [
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at' => null,
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('contracts.fill_expired_count', 0)
            ->assertJsonPath('contracts.fill_expired', [])
            ->assertJsonPath('contracts.sms_expired_count', 0);
    }

    public function test_sms_expired_is_a_separate_counter_including_other_school(): void
    {
        $this->asSuperadmin();
        $other = Partner::factory()->create(['title' => 'Другая школа']);
        $here = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Смирнов',
            'name' => 'Илья',
        ]);
        $there = $this->createUserWithRole('user', $other, [
            'lastname' => 'Козлов',
            'name' => 'Олег',
        ]);
        $smsAt = now()->subMinutes(10);
        $this->makeContract($here, $this->partner, [
            'status' => Contract::STATUS_EXPIRED,
            'updated_at' => $smsAt,
        ]);
        $this->makeContract($there, $other, [
            'status' => Contract::STATUS_EXPIRED,
            'updated_at' => $smsAt->copy()->subMinute(),
        ]);
        $this->makeContract($here, $this->partner, [
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('contracts.fill_expired_count', 1)
            ->assertJsonPath('contracts.sms_expired_count', 2)
            ->assertJsonPath('contracts.sms_expired.0.name', 'Смирнов Илья')
            ->assertJsonPath('contracts.sms_expired.0.school', (string) $this->partner->title)
            ->assertJsonPath('contracts.sms_expired.1.name', 'Козлов Олег')
            ->assertJsonPath('contracts.sms_expired.1.school', 'Другая школа');

        $this->assertSame($smsAt->getTimestamp(), $response->json('contracts.sms_expired.0.at'));
        $this->assertCount(1, $response->json('contracts.fill_expired'));
        $this->assertSame(1, (int) $response->json('contracts.fill_expired_count'));
    }

    public function test_empty_fio_and_school_title_use_fallbacks(): void
    {
        $this->asSuperadmin();
        $namelessSchool = Partner::factory()->create(['title' => '']);
        $student = $this->createUserWithRole('user', $namelessSchool, [
            'lastname' => '',
            'name' => '',
        ]);
        $this->makeContract($student, $namelessSchool, [
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at' => now()->subHour(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('contracts.fill_expired_count', 1)
            ->assertJsonPath('contracts.fill_expired.0.name', '#'.$student->id)
            ->assertJsonPath('contracts.fill_expired.0.school', 'Без школы');
    }

    public function test_hover_list_is_capped_while_count_is_full(): void
    {
        $this->asSuperadmin();
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Кап',
            'name' => 'Двадцать',
        ]);
        $newest = null;
        for ($i = 0; $i < 21; $i++) {
            $newest = $this->makeContract($student, $this->partner, [
                'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
                'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
                'fill_expires_at' => now()->subHours(21 - $i),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('contracts.fill_expired_count', 21);

        $rows = $response->json('contracts.fill_expired');
        $this->assertIsArray($rows);
        $this->assertCount(OpsMonitor::CONTRACTS_HOVER_LIMIT, $rows);
        $this->assertSame($newest->fill_expires_at->getTimestamp(), $rows[0]['at']);
    }

    public function test_expired_status_does_not_also_count_as_fill_expired(): void
    {
        $this->asSuperadmin();
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => 'Смс',
            'name' => 'Только',
        ]);
        $this->makeContract($student, $this->partner, [
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_EXPIRED,
            'fill_expires_at' => now()->subDays(40),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('contracts.fill_expired_count', 0)
            ->assertJsonPath('contracts.fill_expired', [])
            ->assertJsonPath('contracts.sms_expired_count', 1)
            ->assertJsonPath('contracts.sms_expired.0.name', 'Смс Только');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeContract(User $student, Partner $school, array $overrides = []): Contract
    {
        $suffix = (string) random_int(100000, 999999);

        $contract = Contract::create(array_merge([
            'school_id' => $school->id,
            'user_id' => $student->id,
            'group_id' => null,
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'source_pdf_path' => 'documents/2026/09/ops-'.$suffix.'.pdf',
            'source_sha256' => hash('sha256', 'ops-contract-'.$suffix),
            'provider' => 'podpislon',
            'status' => Contract::STATUS_DRAFT,
        ], $overrides));

        if (array_key_exists('updated_at', $overrides)) {
            $contract->forceFill(['updated_at' => $overrides['updated_at']])->saveQuietly();
        }

        return $contract->refresh();
    }
}
