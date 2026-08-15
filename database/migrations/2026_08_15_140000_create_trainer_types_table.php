<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Типы тренера: партнёрский шаблон ставок Канзаса.
 * Системный тип «Главный тренер» (неудаляемый) назначается всем профилям.
 */
return new class extends Migration
{
    private const SYSTEM_NAME = 'Главный тренер';

    public function up(): void
    {
        Schema::create('trainer_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_system')->default(false);
            $table->bigInteger('rate_per_training_cents')->default(0);
            $table->bigInteger('base_premium_cents')->default(0);
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            $table->unique(['partner_id', 'name']);
            $table->index(['partner_id', 'is_system']);
        });

        Schema::table('trainer_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('trainer_type_id')->nullable()->after('user_id');
        });

        $now = Carbon::now();
        $partnerIds = DB::table('partners')->pluck('id');

        foreach ($partnerIds as $partnerId) {
            $partnerId = (int) $partnerId;
            $typeId = DB::table('trainer_types')->insertGetId([
                'partner_id' => $partnerId,
                'name' => self::SYSTEM_NAME,
                'sort_order' => 0,
                'is_enabled' => 1,
                'is_system' => 1,
                'rate_per_training_cents' => 0,
                'base_premium_cents' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('trainer_profiles')
                ->where('partner_id', $partnerId)
                ->whereNull('trainer_type_id')
                ->update(['trainer_type_id' => $typeId]);
        }

        $leftovers = DB::table('trainer_profiles')->whereNull('trainer_type_id')->get(['id', 'partner_id']);
        foreach ($leftovers as $profile) {
            $typeId = DB::table('trainer_types')
                ->where('partner_id', (int) $profile->partner_id)
                ->where('is_system', 1)
                ->value('id');
            if ($typeId === null) {
                $typeId = DB::table('trainer_types')->insertGetId([
                    'partner_id' => (int) $profile->partner_id,
                    'name' => self::SYSTEM_NAME,
                    'sort_order' => 0,
                    'is_enabled' => 1,
                    'is_system' => 1,
                    'rate_per_training_cents' => 0,
                    'base_premium_cents' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('trainer_profiles')
                ->where('id', $profile->id)
                ->update(['trainer_type_id' => $typeId]);
        }

        // Native ALTER: в проекте нет doctrine/dbal, ->change() на Laravel 10 падает.
        DB::statement('ALTER TABLE `trainer_profiles` MODIFY `trainer_type_id` BIGINT UNSIGNED NOT NULL');

        Schema::table('trainer_profiles', function (Blueprint $table) {
            $table->foreign('trainer_type_id')->references('id')->on('trainer_types')->restrictOnDelete();
        });

        $this->rewriteKansasDraftsFromTypes();
    }

    public function down(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table) {
            $table->dropForeign(['trainer_type_id']);
            $table->dropColumn('trainer_type_id');
        });

        Schema::dropIfExists('trainer_types');
    }

    private function rewriteKansasDraftsFromTypes(): void
    {
        if (! Schema::hasTable('trainer_salary_kansas_draft_trainers')) {
            return;
        }

        $rows = DB::table('trainer_salary_kansas_draft_trainers as k')
            ->join('trainer_salary_draft_lines as l', 'l.id', '=', 'k.trainer_salary_draft_line_id')
            ->join('trainer_salary_periods as p', 'p.id', '=', 'l.trainer_salary_period_id')
            ->join('trainer_profiles as tp', 'tp.id', '=', 'l.trainer_profile_id')
            ->join('trainer_types as t', 't.id', '=', 'tp.trainer_type_id')
            ->where('p.scheme_code', 'kansas')
            ->select([
                'k.id as settings_id',
                'l.id as line_id',
                't.rate_per_training_cents',
                't.base_premium_cents',
            ])
            ->get();

        foreach ($rows as $row) {
            DB::table('trainer_salary_kansas_draft_trainers')
                ->where('id', $row->settings_id)
                ->update([
                    'rate_per_training_cents' => (int) $row->rate_per_training_cents,
                    'base_premium_cents' => (int) $row->base_premium_cents,
                    'updated_at' => Carbon::now(),
                ]);
            DB::table('trainer_salary_draft_lines')
                ->where('id', $row->line_id)
                ->update([
                    'rate_per_training_cents' => (int) $row->rate_per_training_cents,
                    'updated_at' => Carbon::now(),
                ]);
        }
    }
};
