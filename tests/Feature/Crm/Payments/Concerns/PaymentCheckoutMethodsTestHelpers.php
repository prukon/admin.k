<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\Concerns;

use App\Models\PaymentSystem;
use App\Models\UserPrice;
use Illuminate\Support\Facades\DB;

trait PaymentCheckoutMethodsTestHelpers
{
    /**
     * @param  list<string>  $permissions
     */
    protected function grantCheckoutMethodPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function revokeCheckoutMethodPermissions(array $permissions): void
    {
        $ids = array_map(fn (string $slug) => $this->permissionId($slug), $permissions);

        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $this->user->role_id)
            ->whereIn('permission_id', $ids)
            ->delete();

        $this->user->refresh();
        $this->user->unsetRelation('role');
        $this->actingAs($this->user);
    }

    protected function seedReadyMonthlyCheckout(): int
    {
        $this->seedGlobalTbank();
        ['team' => $team] = $this->seedTbankTeamChainForStudent();
        UserPrice::factory()
            ->forUserAndMonth((int) $this->user->id, '2027-09-01', 2500, false, (int) $team->id)
            ->create();

        return (int) $team->id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function monthlyCheckoutPayload(int $teamId): array
    {
        return [
            'paymentDate' => 'Сентябрь 2027',
            'formatedPaymentDate' => '2027-09-01',
            'team_id' => $teamId,
            'outSum' => '25.00',
        ];
    }

    protected function seedReadyClubFeeCheckout(): void
    {
        $this->seedGlobalTbank();
        $this->seedTbankTeamChainForStudent();
        $this->grantCheckoutMethodPermissions(['payment.clubfee']);
    }

    protected function seedRobokassaForCurrentPartner(): void
    {
        PaymentSystem::factory()->robokassa()->create(['partner_id' => $this->partner->id]);
    }

    protected function assertHtmlIsNonEmptyPage(\Illuminate\Testing\TestResponse $response): void
    {
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim(strip_tags((string) $response->getContent())));
        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertStringNotContainsString('application/json', $contentType);
    }
}
