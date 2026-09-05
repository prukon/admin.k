<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\Platform\Concerns;

use App\Models\PartnerPayment;
use App\Models\User;

trait PlatformPaymentsMethodTestHelpers
{
    /**
     * Витринные права родителей — не должны открывать способы оплаты платформы.
     *
     * @return list<string>
     */
    protected function marketplacePaymentMethodPermissions(): array
    {
        return [
            'payment.method.tbankSBP',
            'payment.method.tbankCard',
            'payment.method.robokassa',
        ];
    }

    protected function actorWithMarketplaceMethodsAndWalletView(): User
    {
        return $this->userWithOnlyPermissions(array_merge(
            ['partnerWallet.view'],
            $this->marketplacePaymentMethodPermissions()
        ));
    }

    protected function actorWithMarketplaceMethodsAndServiceView(): User
    {
        return $this->userWithOnlyPermissions(array_merge(
            ['servicePayments.view'],
            $this->marketplacePaymentMethodPermissions()
        ));
    }

    protected function serviceRechargeCardHtml(string $html): string
    {
        $cardPos = strpos($html, 'Оплата сервиса');
        $this->assertNotFalse($cardPos, 'На странице нет блока «Оплата сервиса»');
        $formEnd = strpos($html, '</form>', $cardPos);
        $this->assertNotFalse($formEnd, 'Не нашли конец формы абонплаты');

        return substr($html, $cardPos, $formEnd - $cardPos);
    }

    protected function assertRadioChecked(string $html, string $id): void
    {
        $this->assertMatchesRegularExpression(
            '/id="'.preg_quote($id, '/').'"[^>]*checked/',
            $html,
            "Ожидали checked у #{$id}"
        );
    }

    protected function assertRadioNotChecked(string $html, string $id): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/id="'.preg_quote($id, '/').'"[^>]*checked/',
            $html,
            "Не ожидали checked у #{$id}"
        );
    }

    protected function assertFieldSlotContains(string $html, string $field, string $message): void
    {
        $this->assertTrue(
            (bool) preg_match(
                '/data-error-for="'.preg_quote($field, '/').'"[^>]*>\s*'.preg_quote($message, '/').'\s*<\/div>/u',
                $html
            ),
            "Ожидали «{$message}» в data-error-for=\"{$field}\""
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function walletTopupPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => 100,
            'partner_id' => (int) $this->partner->id,
            'payment_method' => 'tinkoff_sbp',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function servicePayPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => 2500,
            'days' => 29,
            'partner_id' => (int) $this->partner->id,
            'description' => 'Учет до 200 пользователей',
            'payment_method' => 'tinkoff_sbp',
        ], $overrides);
    }

    protected function assertNoPartnerPayment(): void
    {
        $this->assertSame(0, (int) PartnerPayment::query()->count());
    }

    /**
     * Поверхности кошелька и абонплаты без вызова шлюза (POST без суммы).
     *
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    protected function platformPaymentSurfaceEndpointsWithoutGateway(): array
    {
        return [
            ['method' => 'GET', 'url' => route('partner.wallet')],
            ['method' => 'GET', 'url' => route('partner.wallet.success')],
            ['method' => 'GET', 'url' => $this->walletTransactionsUrl()],
            ['method' => 'GET', 'url' => route('partner.payment.recharge')],
            ['method' => 'GET', 'url' => route('partner.payment.history')],
            ['method' => 'GET', 'url' => route('partner.payment.success')],
            [
                'method' => 'GET',
                'url' => route('partner.payment.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 25,
                ]),
            ],
            [
                'method' => 'POST',
                'url' => route('partner.wallet.topup'),
                'data' => ['partner_id' => (int) $this->partner->id],
            ],
            [
                'method' => 'POST',
                'url' => route('partner.payment.tinkoff.sbp'),
                'data' => [
                    'partner_id' => (int) $this->partner->id,
                    'description' => 'Учет до 200 пользователей',
                    'days' => 29,
                ],
            ],
        ];
    }
}
