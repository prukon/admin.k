<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\LessonPackage;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\UserLessonPackage;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * HTTP-init оплат с team_id: клубный взнос, абонемент (T‑Bank / Robokassa).
 */
final class PayableTeamPaymentInitFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantClubFeeAccess(): void
    {
        $this->grantPermission('payment.clubfee');
    }

    private function grantAllPaymentInitPermissions(): void
    {
        foreach ([
            'paying.classes',
            'payment.method.robokassa',
            'payment.method.tbankCard',
        ] as $perm) {
            $this->grantPermission($perm);
        }
    }

    private function seedRobokassa(): void
    {
        PaymentSystem::factory()
            ->robokassa()
            ->create(['partner_id' => $this->partner->id]);
    }

    /**
     * @return array{0: Team, 1: Team}
     */
    private function attachStudentToTwoTeams(): array
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Club-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Club-B']);
        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($this->user, (int) $teamA->id);
        $sync->attachTeamForStudent($this->user, (int) $teamB->id);

        return [$teamA, $teamB];
    }

    public function test_club_fee_page_guest_is_denied(): void
    {
        Auth::logout();

        $response = $this->get(route('clubFee'));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_club_fee_page_forbidden_without_clubfee_permission(): void
    {
        $denied = $this->createUserWithoutPermission('payment.clubfee', $this->partner);
        $this->actingAs($denied);

        $this->get(route('clubFee'))->assertForbidden();
    }

    public function test_club_fee_page_ok_with_single_team(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Единственная']);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        $this->grantClubFeeAccess();

        $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewHas('clubFeeBlocked', false)
            ->assertViewHas('clubFeeRequiresTeamChoice', false)
            ->assertViewHas('clubFeeDefaultTeamId', (int) $team->id);
    }

    public function test_club_fee_page_requires_team_choice_with_multiple_teams(): void
    {
        $this->attachStudentToTwoTeams();
        $this->grantClubFeeAccess();

        $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewHas('clubFeeBlocked', false)
            ->assertViewHas('clubFeeRequiresTeamChoice', true)
            ->assertViewHas('clubFeeDefaultTeamId', null);
    }

    public function test_club_fee_robokassa_non_ajax_single_team_sets_meta_team_id(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        $this->grantAllPaymentInitPermissions();
        $this->seedRobokassa();

        $response = $this->post(route('payment.pay'), [
            'outSum' => '1500.00',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect();
        $this->assertNotSame(200, $response->getStatusCode());

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame('club_fee', (string) $payable->type);
        $this->assertSame((int) $team->id, (int) ($payable->meta['team_id'] ?? 0));

        $intent = PaymentIntent::query()->latest('id')->first();
        $meta = json_decode((string) $intent->meta, true);
        $this->assertSame((int) $team->id, (int) ($meta['team_id'] ?? 0));
    }

    public function test_club_fee_robokassa_non_ajax_multiple_teams_requires_team_id(): void
    {
        $this->attachStudentToTwoTeams();
        $this->grantAllPaymentInitPermissions();
        $this->seedRobokassa();

        $this->post(route('payment.pay'), [
            'outSum' => '2000.00',
        ])->assertStatus(422);

        $this->assertSame(0, Payable::query()->where('type', 'club_fee')->count());
    }

    public function test_club_fee_robokassa_non_ajax_multiple_teams_with_team_id_creates_payable(): void
    {
        [$teamA, $teamB] = $this->attachStudentToTwoTeams();
        $this->grantAllPaymentInitPermissions();
        $this->seedRobokassa();

        $response = $this->post(route('payment.pay'), [
            'outSum' => '2200.00',
            'team_id' => (int) $teamB->id,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect();

        $payable = Payable::query()->latest('id')->first();
        $this->assertSame('club_fee', (string) $payable->type);
        $this->assertSame((int) $teamB->id, (int) ($payable->meta['team_id'] ?? 0));
        $this->assertNotSame((int) $teamA->id, (int) ($payable->meta['team_id'] ?? 0));
    }

    public function test_tinkoff_init_lesson_package_persists_team_id_in_payable_meta(): void
    {
        $this->grantPermission('payment.method.tbankCard');
        $this->grantPermission('paying.classes');

        $this->partner->tinkoff_partner_id = 'SHOP-TEAM-ULP';
        $this->partner->save();

        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_TEAM',
            'token_password' => 'PWD_TEAM',
            'e2c_terminal_key' => 'E2C_TEAM',
            'e2c_token_password' => 'E2C_PWD',
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        $package = LessonPackage::factory()->create(['partner_id' => $this->partner->id]);

        $ulp = UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 4,
            'lessons_remaining' => 4,
            'fee_amount' => '890.00',
            'is_paid' => false,
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return Http::response([
                    'Success' => true,
                    'PaymentId' => 990001,
                    'PaymentURL' => 'https://example.test/pay-team-ulp',
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $response = $this->post(route('payment.tinkoff.pay'), [
            'payment_kind' => 'lesson_package',
            'user_lesson_package_id' => $ulp->id,
            'paymentDate' => 'ignored',
        ]);

        $response->assertRedirect('https://example.test/pay-team-ulp');

        $payable = Payable::query()->latest('id')->first();
        $this->assertSame('lesson_package_fee', (string) $payable->type);
        $this->assertSame((int) $team->id, (int) ($payable->meta['team_id'] ?? 0));
        $this->assertSame((int) $ulp->id, (int) ($payable->meta['user_lesson_package_id'] ?? 0));
    }
}
