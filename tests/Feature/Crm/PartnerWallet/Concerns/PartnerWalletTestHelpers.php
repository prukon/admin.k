<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet\Concerns;

use App\Models\PartnerWalletTransaction;
use Illuminate\Testing\TestResponse;

trait PartnerWalletTestHelpers
{
    /**
     * @return array<string, string>
     */
    protected function walletAjaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function walletTransactionsUrl(array $query = []): string
    {
        return route('partner.wallet.transactions', array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ], $query));
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    protected function walletAuthEndpoints(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('partner.wallet'),
            ],
            [
                'method' => 'GET',
                'url' => $this->walletTransactionsUrl(),
            ],
            [
                'method' => 'GET',
                'url' => route('partner.wallet.success'),
            ],
            [
                'method' => 'POST',
                'url' => route('partner.wallet.topup'),
                'data' => [
                    'amount' => 100,
                    'partner_id' => (int) $this->partner->id,
                ],
            ],
        ];
    }

    /**
     * POST topup без суммы — 422/302, без вызова ЮKassa.
     *
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    protected function walletAuthEndpointsWithoutGateway(): array
    {
        $endpoints = $this->walletAuthEndpoints();
        foreach ($endpoints as $i => $item) {
            if ($item['method'] === 'POST') {
                $endpoints[$i]['data'] = [
                    'partner_id' => (int) $this->partner->id,
                ];
            }
        }

        return $endpoints;
    }

    /**
     * @param  list<int>  $allowed
     */
    protected function assertWalletEndpointsStatus(
        array $endpoints,
        array $allowed,
        string $label,
        bool $asJson = true,
    ): void {
        foreach ($endpoints as $item) {
            if ($asJson) {
                $response = $this->json($item['method'], $item['url'], $item['data'] ?? []);
            } else {
                $server = ['HTTP_ACCEPT' => 'text/html'];
                $payload = $item['data'] ?? [];
                if ($item['method'] !== 'GET') {
                    $payload['_token'] = csrf_token();
                }
                $response = $this->call($item['method'], $item['url'], $payload, [], [], $server);
            }

            $this->assertContains(
                $response->getStatusCode(),
                $allowed,
                "{$label} [".($asJson ? 'JSON' : 'web')."]: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode(), "{$label}: неожиданный 500 на {$item['method']} {$item['url']}");
            if ($response->getStatusCode() === 200) {
                $this->assertNotSame(
                    '',
                    trim((string) $response->getContent()),
                    "Пустой 200: {$item['method']} {$item['url']}"
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<int>
     */
    protected function walletTxIds(array $json): array
    {
        return collect($json['data'] ?? [])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeWalletTx(int $partnerId, int $userId, string $description, array $overrides = []): PartnerWalletTransaction
    {
        return PartnerWalletTransaction::query()->create(array_merge([
            'partner_id' => $partnerId,
            'user_id' => $userId,
            'type' => 'credit',
            'amount_cents' => 10000,
            'currency' => 'RUB',
            'provider' => 'manual',
            'status' => 'succeeded',
            'description' => $description,
            'meta' => null,
        ], $overrides));
    }

    protected function makePendingYookassaTx(int $partnerId, int $userId, int $amountCents = 7000): PartnerWalletTransaction
    {
        return $this->makeWalletTx($partnerId, $userId, 'Пополнение баланса партнёра', [
            'amount_cents' => $amountCents,
            'provider' => 'yookassa',
            'status' => 'pending',
            'payment_id' => 'yk-pending-'.uniqid('', true),
        ]);
    }

    protected function yookassaAllowedIp(): string
    {
        return '77.75.156.11';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postWalletWebhookJson(string $url, array $payload, ?string $ip = null): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip ?? $this->yookassaAllowedIp()])
            ->postJson($url, $payload);
    }

    /**
     * @param  array<string, mixed>  $metadataExtra
     * @return array<string, mixed>
     */
    protected function yookassaWalletWebhookPayload(
        PartnerWalletTransaction $tx,
        string $event = 'payment.succeeded',
        array $metadataExtra = [],
        ?string $amountValue = null,
    ): array {
        $amount = $amountValue ?? number_format(((int) $tx->amount_cents) / 100, 2, '.', '');

        return [
            'event' => $event,
            'object' => [
                'id' => $tx->payment_id ?: 'yk-object-'.$tx->id,
                'amount' => [
                    'value' => $amount,
                    'currency' => 'RUB',
                ],
                'metadata' => array_merge([
                    'wallet_transaction_id' => (string) $tx->id,
                    'partner_id' => (string) $tx->partner_id,
                    'user_id' => (string) ($tx->user_id ?? 0),
                    'scope' => 'partner_wallet_topup',
                ], $metadataExtra),
            ],
        ];
    }

    protected function useDummyYookassaCredentials(): void
    {
        config([
            'yookassa.shop_id' => 999999,
            'yookassa.secret_key' => 'test_secret_isolation',
        ]);
    }

    protected function assertTopupDidNotCreateTransaction(): void
    {
        $this->assertSame(0, (int) PartnerWalletTransaction::query()->count());
    }

    protected function latestWalletTx(): ?PartnerWalletTransaction
    {
        return PartnerWalletTransaction::query()->latest('id')->first();
    }

    protected function assertJsonHasFieldError(TestResponse $response, string $field): void
    {
        $response->assertStatus(422)->assertJsonValidationErrors([$field]);
        $messages = $response->json('errors.'.$field);
        $this->assertIsArray($messages);
        $this->assertNotSame('', trim((string) ($messages[0] ?? '')));
    }
}
