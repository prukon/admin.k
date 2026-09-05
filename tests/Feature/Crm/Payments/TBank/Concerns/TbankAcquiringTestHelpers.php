<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank\Concerns;

use App\Models\TinkoffPayment;
use App\Models\User;
use App\Services\Tinkoff\TinkoffSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

trait TbankAcquiringTestHelpers
{
    /**
     * @return array<string, string>
     */
    protected function acquiringAjaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    protected function fakeAcquiringInit(string $paymentId): void
    {
        Http::fake(function ($request) use ($paymentId) {
            if (str_contains($request->url(), '/v2/Init')) {
                return Http::response([
                    'Success' => true,
                    'PaymentId' => $paymentId,
                    'PaymentURL' => 'https://securepay.tinkoff.ru/'.$paymentId,
                ], 200);
            }

            if (str_contains($request->url(), '/v2/GetQr') || str_contains($request->url(), '/v2/GetState')) {
                $dataType = $request->data()['DataType'] ?? null;

                return Http::response([
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Message' => 'OK',
                    'Data' => $dataType === 'PAYLOAD' ? 'https://qr.nspk.ru/ACQTEST' : 'svg-data',
                    'Status' => 'FORM_SHOWED',
                    'PaymentId' => $paymentId,
                    'Amount' => 10000,
                ], 200);
            }

            return Http::response(['Success' => false, 'Message' => 'unexpected '.$request->url()], 500);
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function signedAcquiringPayload(array $fields, string $password = 'PWD_ACQ'): array
    {
        $fields['TerminalKey'] = $fields['TerminalKey'] ?? 'TERM_ACQ';
        $fields['Success'] = $fields['Success'] ?? true;
        $fields['Token'] = TinkoffSignature::makeToken($fields, $password);

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeAcquiringPayment(array $overrides = []): TinkoffPayment
    {
        return TinkoffPayment::query()->create(array_merge([
            'order_id' => 'acq-'.uniqid('', true),
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'channel' => TinkoffPayment::CHANNEL_ACQUIRING,
            'status' => 'FORM',
            'tinkoff_payment_id' => '991100',
        ], $overrides));
    }

    protected function grantNamedPermission(User $actor, string $permissionName, ?int $partnerId = null): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $partnerId ?? (int) $this->partner->id,
            'role_id' => (int) $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function userWithOnlyPermissions(array $permissions): User
    {
        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'test_acq_'.uniqid('', true),
            'label' => 'Test acquiring',
            'is_sistem' => 0,
            'order_by' => 0,
            'is_visible' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
        ]);

        foreach ($permissions as $permission) {
            $this->grantNamedPermission($user, $permission);
        }

        return $user;
    }
}
