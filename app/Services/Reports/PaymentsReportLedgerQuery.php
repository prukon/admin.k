<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Строки отчёта «Платежи»: журнал payments и ручные отметки is_manual_paid = 1.
 * Id ручных строк отрицательные, чтобы не совпасть с payments.id (возврат, чек, выплата).
 */
final class PaymentsReportLedgerQuery
{
    public const ORIGIN_GATEWAY = 'gateway';

    public const ORIGIN_MANUAL = 'manual';

    private const PACKAGE_ID_OFFSET = 1000000000;

    private const CUSTOM_ID_OFFSET = 2000000000;

    public function union(int $partnerId): Builder
    {
        return $this->gateway($partnerId)
            ->unionAll($this->manualMonth($partnerId))
            ->unionAll($this->manualPackage($partnerId))
            ->unionAll($this->manualCustom($partnerId));
    }

    private function gateway(int $partnerId): Builder
    {
        return DB::table('payments')
            ->join('users as ledger_users', 'ledger_users.id', '=', 'payments.user_id')
            ->where('ledger_users.partner_id', $partnerId)
            ->selectRaw("
                CAST(payments.id AS SIGNED) as id,
                payments.partner_id as partner_id,
                payments.user_id as user_id,
                payments.user_name as user_name,
                payments.team_id as team_id,
                payments.team_title as team_title,
                payments.location_id as location_id,
                payments.operation_date as operation_date,
                payments.payment_month as payment_month,
                payments.summ_cents as summ_cents,
                payments.payment_number as payment_number,
                payments.deal_id as deal_id,
                payments.payment_id as payment_id,
                payments.payment_status as payment_status,
                payments.created_at as created_at,
                payments.updated_at as updated_at,
                'gateway' as payment_origin
            ");
    }

    private function manualMonth(int $partnerId): Builder
    {
        return $this->manualSelect(
            DB::table('users_prices')
                ->join('users as ledger_users', 'ledger_users.id', '=', 'users_prices.user_id')
                ->leftJoin('teams as ledger_teams', 'ledger_teams.id', '=', 'users_prices.team_id')
                ->where('ledger_users.partner_id', $partnerId)
                ->where('users_prices.is_manual_paid', 1),
            'CAST(-users_prices.id AS SIGNED)',
            'users_prices.user_id',
            'users_prices.team_id',
            'users_prices.manual_paid_at',
            "DATE_FORMAT(users_prices.new_month, '%Y-%m-%d')",
            'COALESCE(users_prices.price_cents, 0)',
            'users_prices.created_at',
            'users_prices.updated_at',
        );
    }

    private function manualPackage(int $partnerId): Builder
    {
        $offset = self::PACKAGE_ID_OFFSET;

        return $this->manualSelect(
            DB::table('user_lesson_packages')
                ->join('users as ledger_users', 'ledger_users.id', '=', 'user_lesson_packages.user_id')
                ->leftJoin('teams as ledger_teams', 'ledger_teams.id', '=', 'user_lesson_packages.team_id')
                ->where('ledger_users.partner_id', $partnerId)
                ->where('user_lesson_packages.is_manual_paid', 1),
            "CAST(-({$offset} + user_lesson_packages.id) AS SIGNED)",
            'user_lesson_packages.user_id',
            'user_lesson_packages.team_id',
            'user_lesson_packages.manual_paid_at',
            "'Абонемент'",
            'COALESCE(user_lesson_packages.fee_amount_cents, 0)',
            'user_lesson_packages.created_at',
            'user_lesson_packages.updated_at',
        );
    }

    private function manualCustom(int $partnerId): Builder
    {
        $offset = self::CUSTOM_ID_OFFSET;

        return $this->manualSelect(
            DB::table('user_custom_payment')
                ->join('users as ledger_users', 'ledger_users.id', '=', 'user_custom_payment.user_id')
                ->leftJoin('teams as ledger_teams', 'ledger_teams.id', '=', 'user_custom_payment.team_id')
                ->where('user_custom_payment.partner_id', $partnerId)
                ->where('ledger_users.partner_id', $partnerId)
                ->where('user_custom_payment.is_manual_paid', 1),
            "CAST(-({$offset} + user_custom_payment.id) AS SIGNED)",
            'user_custom_payment.user_id',
            'user_custom_payment.team_id',
            'user_custom_payment.manual_paid_at',
            "'Дополнительный платеж'",
            'COALESCE(user_custom_payment.amount_cents, 0)',
            'user_custom_payment.created_at',
            'user_custom_payment.updated_at',
        );
    }

    private function manualSelect(
        Builder $query,
        string $idExpr,
        string $userIdExpr,
        string $teamIdExpr,
        string $operationDateExpr,
        string $paymentMonthExpr,
        string $summCentsExpr,
        string $createdAtExpr,
        string $updatedAtExpr,
    ): Builder {
        return $query->selectRaw("
            {$idExpr} as id,
            ledger_users.partner_id as partner_id,
            {$userIdExpr} as user_id,
            NULLIF(TRIM(CONCAT_WS(' ', ledger_users.lastname, ledger_users.name)), '') as user_name,
            {$teamIdExpr} as team_id,
            ledger_teams.title as team_title,
            ledger_teams.location_id as location_id,
            {$operationDateExpr} as operation_date,
            {$paymentMonthExpr} as payment_month,
            {$summCentsExpr} as summ_cents,
            NULL as payment_number,
            NULL as deal_id,
            NULL as payment_id,
            NULL as payment_status,
            {$createdAtExpr} as created_at,
            {$updatedAtExpr} as updated_at,
            'manual' as payment_origin
        ");
    }
}
