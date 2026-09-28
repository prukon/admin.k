<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

use App\Models\FiscalReceipt;
use App\Models\UserTableSetting;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;

/**
 * Вкладка «История платежей»: фильтры своей школы, колонки и «Показать N».
 *
 * @see /docs/documentation/partner-wallet.html
 */
final class PartnerWalletHistoryFeatureTest extends CrmTestCase
{
    use PartnerWalletTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
    }

    public function test_wallet_page_has_balance_tab_presets_and_history_toolbar(): void
    {
        $html = $this->get(route('partner.wallet'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('nav-link active', $html);
        $this->assertStringContainsString('/partner-wallet/history', $html);
        $this->assertStringContainsString('data-amount="1000"', $html);
        $this->assertStringContainsString('data-amount="5000"', $html);
        $this->assertStringContainsString('data-amount="10000"', $html);
        $this->assertStringContainsString('>Пополнить<', $html);
        $this->assertStringContainsString('payments-report-total-value', $html);
        $this->assertStringNotContainsString('display-6', $html);
        $this->assertStringNotContainsString('id="walletTxTable"', $html);

        $amountPos = strpos($html, 'id="walletTopupAmount"');
        $this->assertNotFalse($amountPos);
        $this->assertDoesNotMatchRegularExpression('/\bvalue="[^"]+"/', substr($html, $amountPos, 220));

        $history = $this->get(route('partner.wallet.history'))
            ->assertOk()
            ->assertViewHas('walletHistoryPageLength', 10)
            ->getContent();

        $this->assertStringContainsString('id="wallet-history-filters"', $history);
        $this->assertStringContainsString('id="walletHistoryColumnsDropdown"', $history);
        $this->assertStringContainsString('id="walletColReceipt"', $history);
        $this->assertStringContainsString('data-column-key="receipt"', $history);
        $this->assertStringContainsString('renderWalletReceiptCell', $history);
        $this->assertStringContainsString('Чек сформирован', $history);
        $this->assertStringContainsString('Чек не сформирован', $history);
        $this->assertStringContainsString("provider_code !== 'tinkoff'", $history);
        $this->assertStringContainsString('persistPageLength: true', $history);
        $this->assertStringContainsString('payments-report-surface', $history);
        $this->assertStringNotContainsString('id="reloadTable"', $history);
        $this->assertStringNotContainsString('id="walletTopupForm"', $history);
    }

    public function test_filters_keep_only_current_partner_rows(): void
    {
        $old = $this->makeWalletTx($this->partner->id, $this->user->id, 'hist-old-debit', [
            'type' => 'debit',
            'status' => 'canceled',
            'provider' => 'manual',
            'amount_cents' => 7000,
        ]);
        $old->forceFill(['created_at' => '2026-01-15 12:00:00'])->save();

        $recent = $this->makeWalletTx($this->partner->id, $this->user->id, 'hist-recent-credit', [
            'type' => 'credit',
            'status' => 'succeeded',
            'provider' => 'yookassa',
            'amount_cents' => 500000,
        ]);
        $recent->forceFill(['created_at' => '2026-06-02 12:00:00'])->save();

        $foreign = $this->makeWalletTx($this->foreignPartner->id, $this->foreignUser->id, 'hist-foreign', [
            'type' => 'credit',
            'status' => 'succeeded',
            'provider' => 'yookassa',
        ]);
        $foreign->forceFill(['created_at' => '2026-06-02 12:00:00'])->save();

        $byDate = $this->getJson($this->walletTransactionsUrl([
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]), $this->walletAjaxHeaders())->assertOk()->json();

        $this->assertSame([$recent->id], $this->walletTxIds($byDate));
        $this->assertSame('Пополнение', collect($byDate['data'])->first()['type']);
        $this->assertSame('ЮKassa', collect($byDate['data'])->first()['provider']);

        $byType = $this->getJson($this->walletTransactionsUrl([
            'type' => 'debit',
        ]), $this->walletAjaxHeaders())->assertOk()->json();
        $this->assertSame([$old->id], $this->walletTxIds($byType));
        $this->assertSame('Списание за договор', collect($byType['data'])->first()['provider']);
    }

    public function test_invalid_filter_returns_field_error_under_type(): void
    {
        $this->getJson($this->walletTransactionsUrl([
            'type' => 'nope',
        ]), $this->walletAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.type.0', 'Некорректный тип операции.');
    }

    public function test_columns_and_page_length_are_saved_for_current_user(): void
    {
        $this->postJson(route('partner.wallet.transactions.columns-settings.save'), [
            'columns' => [
                'id' => true,
                'created_at' => true,
                'provider' => false,
            ],
        ], $this->walletAjaxHeaders())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->getJson(route('partner.wallet.transactions.columns-settings.get'), $this->walletAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('id', true)
            ->assertJsonPath('provider', false);

        $this->postJson(route('partner.wallet.transactions.columns-settings.save'), [
            'page_length' => 50,
        ], $this->walletAjaxHeaders())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertSame(50, UserTableSetting::pageLengthForUser((int) $this->user->id, 'partner_wallet_transactions'));
        $this->assertTrue((bool) UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'partner_wallet_transactions')
            ->value('columns')['id']);

        $this->postJson(route('partner.wallet.transactions.columns-settings.save'), [
            'page_length' => 7,
        ], $this->walletAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page_length']);
    }

    public function test_non_ajax_columns_settings_without_payload_redirects_with_field_error(): void
    {
        $this->from(route('partner.wallet'))
            ->post(route('partner.wallet.transactions.columns-settings.save'), [
                '_token' => csrf_token(),
            ])
            ->assertRedirect(route('partner.wallet'))
            ->assertSessionHasErrors(['columns']);
    }

    public function test_receipt_column_uses_latest_income_url_only_for_display_rules(): void
    {
        $tinkoff = $this->makeWalletTx($this->partner->id, $this->user->id, 'topup-tinkoff', [
            'provider' => 'tinkoff',
            'status' => 'succeeded',
            'amount_cents' => 10000,
        ]);
        $this->makeWalletReceipt($tinkoff->id, 'https://receipts.ru/old');
        $this->makeWalletReceipt($tinkoff->id, 'https://receipts.ru/latest');
        $this->makeWalletReceipt($tinkoff->id, 'https://receipts.ru/return', FiscalReceipt::TYPE_INCOME_RETURN);

        $pending = $this->makeWalletTx($this->partner->id, $this->user->id, 'topup-pending', [
            'provider' => 'tinkoff',
            'status' => 'pending',
        ]);

        $badUrl = $this->makeWalletTx($this->partner->id, $this->user->id, 'topup-bad-url', [
            'provider' => 'tinkoff',
            'status' => 'succeeded',
        ]);
        $this->makeWalletReceipt($badUrl->id, 'https://example.test/not-ofd');

        $yookassa = $this->makeWalletTx($this->partner->id, $this->user->id, 'topup-yk', [
            'provider' => 'yookassa',
            'status' => 'succeeded',
        ]);

        $manual = $this->makeWalletTx($this->partner->id, $this->user->id, 'contract-fee', [
            'type' => 'debit',
            'provider' => 'manual',
            'status' => 'succeeded',
        ]);

        $foreignReceiptTx = $this->makeWalletTx($this->partner->id, $this->user->id, 'foreign-receipt', [
            'provider' => 'tinkoff',
            'status' => 'succeeded',
        ]);
        $this->makeWalletReceipt($foreignReceiptTx->id, 'https://receipts.ru/foreign', partnerId: (int) $this->foreignPartner->id);

        $json = $this->getJson($this->walletTransactionsUrl(), $this->walletAjaxHeaders())
            ->assertOk()
            ->json();

        $rows = collect($json['data'])->keyBy('id');

        $shown = $rows[$tinkoff->id];
        $this->assertSame('tinkoff', $shown['provider_code']);
        $this->assertSame('T‑Bank', $shown['provider']);
        $this->assertTrue((bool) $shown['has_receipt']);
        $this->assertSame('https://receipts.ru/latest', $shown['receipt_url']);
        $this->assertArrayNotHasKey('fiscal_income_receipt_url', $shown);

        $waiting = $rows[$pending->id];
        $this->assertSame('tinkoff', $waiting['provider_code']);
        $this->assertFalse((bool) $waiting['has_receipt']);
        $this->assertNull($waiting['receipt_url']);

        $invalid = $rows[$badUrl->id];
        $this->assertFalse((bool) $invalid['has_receipt']);
        $this->assertNull($invalid['receipt_url']);

        $yk = $rows[$yookassa->id];
        $this->assertSame('yookassa', $yk['provider_code']);
        $this->assertFalse((bool) $yk['has_receipt']);
        $this->assertNull($yk['receipt_url']);

        $debit = $rows[$manual->id];
        $this->assertSame('manual', $debit['provider_code']);
        $this->assertFalse((bool) $debit['has_receipt']);

        $foreign = $rows[$foreignReceiptTx->id];
        $this->assertFalse((bool) $foreign['has_receipt']);
        $this->assertNull($foreign['receipt_url']);
    }

    private function makeWalletReceipt(
        int $walletTransactionId,
        string $url,
        string $type = FiscalReceipt::TYPE_INCOME,
        ?int $partnerId = null,
    ): FiscalReceipt {
        return FiscalReceipt::query()->create([
            'partner_id' => $partnerId ?? (int) $this->partner->id,
            'provider' => FiscalReceipt::PROVIDER_CLOUDKASSIR,
            'source' => FiscalReceipt::SOURCE_PLATFORM,
            'type' => $type,
            'status' => FiscalReceipt::STATUS_PROCESSED,
            'amount_cents' => 10000,
            'invoice_id' => 'wallet_tx_'.$walletTransactionId.'_'.$type.'_'.uniqid('', true),
            'account_id' => (string) ($partnerId ?? $this->partner->id),
            'idempotency_key' => 'test-wallet-receipt-'.uniqid('', true),
            'wallet_transaction_id' => $walletTransactionId,
            'receipt_url' => $url,
        ]);
    }
}
