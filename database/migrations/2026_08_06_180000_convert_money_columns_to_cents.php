<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Канон денег: все бизнес-суммы → BIGINT копейки (*_cents).
 * T‑Bank amount-поля уже в копейках — не трогаем.
 *
 * Backfill: ROUND(rub * 100). Исторические отчёты сохраняют те же копейки.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->migrateUsersPrices();
        $this->migrateTeamPrices();
        $this->migrateTeamsMonthPrice();
        $this->migrateUserLessonPackages();
        $this->migratePayables();
        $this->migratePaymentIntents();
        $this->migratePayments();
        $this->migrateRefunds();
        $this->migrateFiscalReceipts();
        $this->migrateUserCustomPayment();
        $this->migratePartnerPayments();
        $this->migratePartnerWallet();
        $this->migrateTrainerProfiles();
        $this->migrateTrainerSalaryDraftLines();
        $this->migrateTrainerSalarySnapshots();
    }

    public function down(): void
    {
        $this->revertTrainerSalarySnapshots();
        $this->revertTrainerSalaryDraftLines();
        $this->revertTrainerProfiles();
        $this->revertPartnerWallet();
        $this->revertPartnerPayments();
        $this->revertUserCustomPayment();
        $this->revertFiscalReceipts();
        $this->revertRefunds();
        $this->revertPayments();
        $this->revertPaymentIntents();
        $this->revertPayables();
        $this->revertUserLessonPackages();
        $this->revertTeamsMonthPrice();
        $this->revertTeamPrices();
        $this->revertUsersPrices();
    }

    private function migrateUsersPrices(): void
    {
        if (! Schema::hasTable('users_prices') || Schema::hasColumn('users_prices', 'price_cents')) {
            return;
        }

        Schema::table('users_prices', function (Blueprint $table) {
            $table->bigInteger('price_cents')->default(0)->after('price');
        });

        DB::statement("
            UPDATE users_prices
            SET price_cents = CASE
                WHEN price IS NULL OR TRIM(price) = '' THEN 0
                ELSE ROUND(CAST(REPLACE(TRIM(price), ',', '.') AS DECIMAL(15,2)) * 100)
            END
        ");

        Schema::table('users_prices', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }

    private function revertUsersPrices(): void
    {
        if (! Schema::hasTable('users_prices') || ! Schema::hasColumn('users_prices', 'price_cents')) {
            return;
        }
        if (Schema::hasColumn('users_prices', 'price')) {
            return;
        }

        Schema::table('users_prices', function (Blueprint $table) {
            $table->string('price')->nullable()->after('new_month');
        });

        DB::statement("
            UPDATE users_prices
            SET price = TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(price_cents / 100 AS DECIMAL(15,2))))
        ");
        // Проще: всегда с двумя знаками как строка числа
        DB::statement("UPDATE users_prices SET price = CAST(ROUND(price_cents / 100, 2) AS CHAR)");

        Schema::table('users_prices', function (Blueprint $table) {
            $table->dropColumn('price_cents');
        });
    }

    private function migrateTeamPrices(): void
    {
        if (! Schema::hasTable('team_prices') || Schema::hasColumn('team_prices', 'price_cents')) {
            return;
        }

        Schema::table('team_prices', function (Blueprint $table) {
            $table->bigInteger('price_cents')->nullable()->after('price');
        });

        DB::statement('UPDATE team_prices SET price_cents = ROUND(IFNULL(price, 0) * 100)');

        Schema::table('team_prices', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }

    private function revertTeamPrices(): void
    {
        if (! Schema::hasTable('team_prices') || ! Schema::hasColumn('team_prices', 'price_cents')) {
            return;
        }
        if (Schema::hasColumn('team_prices', 'price')) {
            return;
        }

        Schema::table('team_prices', function (Blueprint $table) {
            $table->integer('price')->nullable()->after('team_id');
        });
        DB::statement('UPDATE team_prices SET price = ROUND(IFNULL(price_cents, 0) / 100)');
        Schema::table('team_prices', function (Blueprint $table) {
            $table->dropColumn('price_cents');
        });
    }

    private function migrateTeamsMonthPrice(): void
    {
        if (! Schema::hasTable('teams') || ! Schema::hasColumn('teams', 'month_price')) {
            return;
        }
        if (Schema::hasColumn('teams', 'month_price_cents')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('month_price_cents')->nullable()->after('month_price');
        });
        DB::statement('UPDATE teams SET month_price_cents = ROUND(month_price * 100) WHERE month_price IS NOT NULL');
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('month_price');
        });
    }

    private function revertTeamsMonthPrice(): void
    {
        if (! Schema::hasTable('teams') || ! Schema::hasColumn('teams', 'month_price_cents')) {
            return;
        }
        if (Schema::hasColumn('teams', 'month_price')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedInteger('month_price')->nullable()->after('default_duration_minutes');
        });
        DB::statement('UPDATE teams SET month_price = ROUND(month_price_cents / 100) WHERE month_price_cents IS NOT NULL');
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('month_price_cents');
        });
    }

    private function migrateUserLessonPackages(): void
    {
        if (! Schema::hasTable('user_lesson_packages') || Schema::hasColumn('user_lesson_packages', 'fee_amount_cents')) {
            return;
        }

        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->bigInteger('fee_amount_cents')->nullable()->after('fee_amount');
        });
        DB::statement('UPDATE user_lesson_packages SET fee_amount_cents = ROUND(fee_amount * 100) WHERE fee_amount IS NOT NULL');
        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->dropColumn('fee_amount');
        });
    }

    private function revertUserLessonPackages(): void
    {
        if (! Schema::hasTable('user_lesson_packages') || ! Schema::hasColumn('user_lesson_packages', 'fee_amount_cents')) {
            return;
        }
        if (Schema::hasColumn('user_lesson_packages', 'fee_amount')) {
            return;
        }

        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->decimal('fee_amount', 15, 2)->nullable()->after('lessons_remaining');
        });
        DB::statement('UPDATE user_lesson_packages SET fee_amount = ROUND(fee_amount_cents / 100, 2) WHERE fee_amount_cents IS NOT NULL');
        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->dropColumn('fee_amount_cents');
        });
    }

    private function migrateDecimalToCents(string $table, string $old, string $new, bool $nullable = false): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, $new)) {
            return;
        }
        if (! Schema::hasColumn($table, $old)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($old, $new, $nullable) {
            if ($nullable) {
                $blueprint->bigInteger($new)->nullable()->after($old);
            } else {
                $blueprint->bigInteger($new)->default(0)->after($old);
            }
        });

        DB::statement("UPDATE `{$table}` SET `{$new}` = ROUND(IFNULL(`{$old}`, 0) * 100)");

        Schema::table($table, function (Blueprint $blueprint) use ($old) {
            $blueprint->dropColumn($old);
        });
    }

    private function revertCentsToDecimal(string $table, string $old, string $new, string $decimalDef = 'decimal(15,2)', bool $nullable = false): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $new)) {
            return;
        }
        if (Schema::hasColumn($table, $old)) {
            return;
        }

        // down: add decimal, fill, drop cents — упрощённо через raw
        Schema::table($table, function (Blueprint $blueprint) use ($old, $new, $nullable) {
            if ($nullable) {
                $blueprint->decimal($old, 15, 2)->nullable();
            } else {
                $blueprint->decimal($old, 15, 2)->default(0);
            }
        });
        DB::statement("UPDATE `{$table}` SET `{$old}` = ROUND(`{$new}` / 100, 2)");
        Schema::table($table, function (Blueprint $blueprint) use ($new) {
            $blueprint->dropColumn($new);
        });
    }

    private function migratePayables(): void
    {
        $this->migrateDecimalToCents('payables', 'amount', 'amount_cents', false);
    }

    private function revertPayables(): void
    {
        $this->revertCentsToDecimal('payables', 'amount', 'amount_cents', 'decimal(15,2)', false);
    }

    private function migratePaymentIntents(): void
    {
        $this->migrateDecimalToCents('payment_intents', 'out_sum', 'out_sum_cents', false);
    }

    private function revertPaymentIntents(): void
    {
        $this->revertCentsToDecimal('payment_intents', 'out_sum', 'out_sum_cents', 'decimal(15,2)', false);
    }

    private function migratePayments(): void
    {
        if (! Schema::hasTable('payments') || Schema::hasColumn('payments', 'summ_cents')) {
            return;
        }
        if (! Schema::hasColumn('payments', 'summ')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->bigInteger('summ_cents')->default(0)->after('summ');
        });
        DB::statement('UPDATE payments SET summ_cents = ROUND(IFNULL(summ, 0) * 100)');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('summ');
        });
    }

    private function revertPayments(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'summ_cents')) {
            return;
        }
        if (Schema::hasColumn('payments', 'summ')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('summ', 15, 2)->default(0);
        });
        DB::statement('UPDATE payments SET summ = ROUND(summ_cents / 100, 2)');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('summ_cents');
        });
    }

    private function migrateRefunds(): void
    {
        $this->migrateDecimalToCents('refunds', 'amount', 'amount_cents', false);
    }

    private function revertRefunds(): void
    {
        $this->revertCentsToDecimal('refunds', 'amount', 'amount_cents');
    }

    private function migrateFiscalReceipts(): void
    {
        $this->migrateDecimalToCents('fiscal_receipts', 'amount', 'amount_cents', false);
    }

    private function revertFiscalReceipts(): void
    {
        $this->revertCentsToDecimal('fiscal_receipts', 'amount', 'amount_cents');
    }

    private function migrateUserCustomPayment(): void
    {
        if (! Schema::hasTable('user_custom_payment')) {
            return;
        }
        $this->migrateDecimalToCents('user_custom_payment', 'amount', 'amount_cents', false);
    }

    private function revertUserCustomPayment(): void
    {
        $this->revertCentsToDecimal('user_custom_payment', 'amount', 'amount_cents', 'decimal(10,2)');
    }

    private function migratePartnerPayments(): void
    {
        $this->migrateDecimalToCents('partner_payments', 'amount', 'amount_cents', false);
    }

    private function revertPartnerPayments(): void
    {
        $this->revertCentsToDecimal('partner_payments', 'amount', 'amount_cents', 'decimal(10,2)');
    }

    private function migratePartnerWallet(): void
    {
        $this->migrateDecimalToCents('partner_wallet_transactions', 'amount', 'amount_cents', false);

        if (Schema::hasTable('partners') && Schema::hasColumn('partners', 'wallet_balance') && ! Schema::hasColumn('partners', 'wallet_balance_cents')) {
            Schema::table('partners', function (Blueprint $table) {
                $table->bigInteger('wallet_balance_cents')->default(0)->after('wallet_balance');
            });
            DB::statement('UPDATE partners SET wallet_balance_cents = ROUND(IFNULL(wallet_balance, 0) * 100)');
            Schema::table('partners', function (Blueprint $table) {
                $table->dropColumn('wallet_balance');
            });
        }
    }

    private function revertPartnerWallet(): void
    {
        $this->revertCentsToDecimal('partner_wallet_transactions', 'amount', 'amount_cents', 'decimal(12,2)');

        if (Schema::hasTable('partners') && Schema::hasColumn('partners', 'wallet_balance_cents') && ! Schema::hasColumn('partners', 'wallet_balance')) {
            Schema::table('partners', function (Blueprint $table) {
                $table->decimal('wallet_balance', 12, 2)->default(0);
            });
            DB::statement('UPDATE partners SET wallet_balance = ROUND(wallet_balance_cents / 100, 2)');
            Schema::table('partners', function (Blueprint $table) {
                $table->dropColumn('wallet_balance_cents');
            });
        }
    }

    private function migrateTrainerProfiles(): void
    {
        if (! Schema::hasTable('trainer_profiles')) {
            return;
        }

        if (Schema::hasColumn('trainer_profiles', 'default_base_salary') && ! Schema::hasColumn('trainer_profiles', 'default_base_salary_cents')) {
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->bigInteger('default_base_salary_cents')->default(0)->after('default_base_salary');
            });
            DB::statement('UPDATE trainer_profiles SET default_base_salary_cents = ROUND(IFNULL(default_base_salary, 0) * 100)');
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->dropColumn('default_base_salary');
            });
        }

        if (Schema::hasColumn('trainer_profiles', 'default_rate_per_training') && ! Schema::hasColumn('trainer_profiles', 'default_rate_per_training_cents')) {
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->bigInteger('default_rate_per_training_cents')->default(0)->after('default_rate_per_training');
            });
            DB::statement('UPDATE trainer_profiles SET default_rate_per_training_cents = ROUND(IFNULL(default_rate_per_training, 0) * 100)');
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->dropColumn('default_rate_per_training');
            });
        }
    }

    private function revertTrainerProfiles(): void
    {
        if (! Schema::hasTable('trainer_profiles')) {
            return;
        }

        if (Schema::hasColumn('trainer_profiles', 'default_base_salary_cents') && ! Schema::hasColumn('trainer_profiles', 'default_base_salary')) {
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->decimal('default_base_salary', 12, 2)->default(0);
            });
            DB::statement('UPDATE trainer_profiles SET default_base_salary = ROUND(default_base_salary_cents / 100, 2)');
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->dropColumn('default_base_salary_cents');
            });
        }

        if (Schema::hasColumn('trainer_profiles', 'default_rate_per_training_cents') && ! Schema::hasColumn('trainer_profiles', 'default_rate_per_training')) {
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->decimal('default_rate_per_training', 12, 2)->default(0);
            });
            DB::statement('UPDATE trainer_profiles SET default_rate_per_training = ROUND(default_rate_per_training_cents / 100, 2)');
            Schema::table('trainer_profiles', function (Blueprint $table) {
                $table->dropColumn('default_rate_per_training_cents');
            });
        }
    }

    private function migrateTrainerSalaryMoneyTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $map = [
            'base_salary' => 'base_salary_cents',
            'rate_per_training' => 'rate_per_training_cents',
            'trainings_amount' => 'trainings_amount_cents',
            'bonuses' => 'bonuses_cents',
            'deductions' => 'deductions_cents',
            'total' => 'total_cents',
        ];

        foreach ($map as $old => $new) {
            if (Schema::hasColumn($table, $old) && ! Schema::hasColumn($table, $new)) {
                Schema::table($table, function (Blueprint $blueprint) use ($old, $new) {
                    $blueprint->bigInteger($new)->default(0)->after($old);
                });
                DB::statement("UPDATE `{$table}` SET `{$new}` = ROUND(IFNULL(`{$old}`, 0) * 100)");
                Schema::table($table, function (Blueprint $blueprint) use ($old) {
                    $blueprint->dropColumn($old);
                });
            }
        }
    }

    private function revertTrainerSalaryMoneyTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $map = [
            'base_salary_cents' => 'base_salary',
            'rate_per_training_cents' => 'rate_per_training',
            'trainings_amount_cents' => 'trainings_amount',
            'bonuses_cents' => 'bonuses',
            'deductions_cents' => 'deductions',
            'total_cents' => 'total',
        ];

        foreach ($map as $new => $old) {
            if (Schema::hasColumn($table, $new) && ! Schema::hasColumn($table, $old)) {
                Schema::table($table, function (Blueprint $blueprint) use ($old) {
                    $blueprint->decimal($old, 12, 2)->default(0);
                });
                DB::statement("UPDATE `{$table}` SET `{$old}` = ROUND(`{$new}` / 100, 2)");
                Schema::table($table, function (Blueprint $blueprint) use ($new) {
                    $blueprint->dropColumn($new);
                });
            }
        }
    }

    private function migrateTrainerSalaryDraftLines(): void
    {
        $this->migrateTrainerSalaryMoneyTable('trainer_salary_draft_lines');
    }

    private function revertTrainerSalaryDraftLines(): void
    {
        $this->revertTrainerSalaryMoneyTable('trainer_salary_draft_lines');
    }

    private function migrateTrainerSalarySnapshots(): void
    {
        $this->migrateTrainerSalaryMoneyTable('trainer_salary_snapshots');
    }

    private function revertTrainerSalarySnapshots(): void
    {
        $this->revertTrainerSalaryMoneyTable('trainer_salary_snapshots');
    }
};
