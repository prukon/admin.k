<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\PartnerLegalEntity;
use App\Models\Payable;
use App\Models\Team;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Services\Payments\PayableTeamResolver;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\Feature\Crm\CrmTestCase;

final class PayableTeamPaymentFlowTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_club_fee_requires_team_when_student_in_multiple_groups(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'B']);
        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($this->user, (int) $teamA->id);
        $sync->attachTeamForStudent($this->user, (int) $teamB->id);

        $this->expectException(UnprocessableEntityHttpException::class);
        app(PayableTeamResolver::class)->resolveOrAbort('club_fee', (int) $this->partner->id, $this->user, null);
    }

    public function test_club_fee_blocked_when_student_has_no_groups(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        app(PayableTeamResolver::class)->resolveOrAbort('club_fee', (int) $this->partner->id, $this->user, null);
    }

    public function test_custom_payment_uses_team_from_admin_record(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        $upp = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'amount' => '100.00',
            'is_paid' => false,
        ]);

        $teamId = app(PayableTeamResolver::class)->resolveOrAbort(
            'custom_payment_fee',
            (int) $this->partner->id,
            $this->user,
            null,
            $upp,
        );

        $this->assertSame((int) $team->id, $teamId);
    }

    public function test_lesson_package_requires_team_when_partner_has_multiple_legal_entities(): void
    {
        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Юрлицо 1',
            'organization_name' => 'Юрлицо 1',
            'is_default' => true,
        ]);
        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Юрлицо 2',
            'organization_name' => 'Юрлицо 2',
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'lesson_package_id' => \App\Models\LessonPackage::factory()->create([
                'partner_id' => $this->partner->id,
            ])->id,
            'team_id' => null,
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount' => '100.00',
            'is_paid' => false,
        ]);

        $this->expectException(UnprocessableEntityHttpException::class);
        app(PayableTeamResolver::class)->resolveOrAbort(
            'lesson_package_fee',
            (int) $this->partner->id,
            $this->user,
            null,
            null,
            $ulp,
        );
    }

    public function test_club_fee_page_blocked_without_groups(): void
    {
        $permId = $this->permissionId('payment.clubfee');
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('clubFee'));
        $response->assertOk();
        $response->assertSee('Оплата недоступна: вы не состоите ни в одной группе', false);
    }
}
