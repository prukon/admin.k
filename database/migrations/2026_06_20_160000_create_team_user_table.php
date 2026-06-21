<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_user', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id');

            $table->timestamps();

            $table->unique(['team_id', 'user_id'], 'team_user_team_user_unique');
            $table->index(['partner_id', 'user_id'], 'team_user_partner_user_idx');
            $table->index(['partner_id', 'team_id'], 'team_user_partner_team_idx');

            $table->foreign('partner_id', 'team_user_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('team_id', 'team_user_team_fk')
                ->references('id')
                ->on('teams')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'team_user_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        $studentRoleId = DB::table('roles')->where('name', 'user')->value('id');

        if ($studentRoleId !== null) {
            DB::table('users')
                ->select(['id', 'partner_id', 'team_id'])
                ->whereNotNull('team_id')
                ->whereNotNull('partner_id')
                ->where('role_id', (int) $studentRoleId)
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    $now = now();
                    $inserts = [];

                    foreach ($rows as $row) {
                        $inserts[] = [
                            'partner_id' => (int) $row->partner_id,
                            'team_id'    => (int) $row->team_id,
                            'user_id'    => (int) $row->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($inserts !== []) {
                        DB::table('team_user')->insertOrIgnore($inserts);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
    }
};
