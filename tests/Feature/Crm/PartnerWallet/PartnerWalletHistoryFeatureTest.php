<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

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
}
