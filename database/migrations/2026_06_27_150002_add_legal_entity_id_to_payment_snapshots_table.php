<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<array{table: string, after: string, index: string, fk: string}> */
    private const TARGETS = [
        [
            'table' => 'tinkoff_payments',
            'after' => 'partner_id',
            'index' => 'tinkoff_payments_legal_entity_idx',
            'fk' => 'tinkoff_payments_legal_entity_fk',
        ],
        [
            'table' => 'tinkoff_payouts',
            'after' => 'partner_id',
            'index' => 'tinkoff_payouts_legal_entity_idx',
            'fk' => 'tinkoff_payouts_legal_entity_fk',
        ],
        [
            'table' => 'fiscal_receipts',
            'after' => 'partner_id',
            'index' => 'fiscal_receipts_legal_entity_idx',
            'fk' => 'fiscal_receipts_legal_entity_fk',
        ],
    ];

    public function up(): void
    {
        foreach (self::TARGETS as $target) {
            $this->addLegalEntityColumn($target);
        }
    }

    public function down(): void
    {
        foreach (self::TARGETS as $target) {
            $this->dropLegalEntityColumn($target);
        }
    }

    /**
     * @param array{table: string, after: string, index: string, fk: string} $target
     */
    private function addLegalEntityColumn(array $target): void
    {
        if (! Schema::hasTable($target['table'])) {
            return;
        }

        Schema::table($target['table'], function (Blueprint $table) use ($target) {
            if (Schema::hasColumn($target['table'], 'legal_entity_id')) {
                return;
            }

            $table->unsignedBigInteger('legal_entity_id')
                ->nullable()
                ->after($target['after']);

            $table->index('legal_entity_id', $target['index']);

            $table->foreign('legal_entity_id', $target['fk'])
                ->references('id')
                ->on('partner_legal_entities')
                ->nullOnDelete();
        });
    }

    /**
     * @param array{table: string, after: string, index: string, fk: string} $target
     */
    private function dropLegalEntityColumn(array $target): void
    {
        if (! Schema::hasTable($target['table'])) {
            return;
        }

        Schema::table($target['table'], function (Blueprint $table) use ($target) {
            if (! Schema::hasColumn($target['table'], 'legal_entity_id')) {
                return;
            }

            $table->dropForeign($target['fk']);
            $table->dropIndex($target['index']);
            $table->dropColumn('legal_entity_id');
        });
    }
};
