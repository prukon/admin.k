<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * СБП обычного эквайринга: platformPayments.method.tbankSbp → acquiringSbp
 * (тот же permission_id, уже выданные роли сохраняются).
 * Карта: новое скрытое право acquiringCard. Существующим школам не выдаётся.
 * Новым школам карта попадает только через role_base_permissions роли admin.
 */
return new class extends Migration
{
    private const GROUP_SLUG = 'platformPayments';

    private const PERM_SBP_OLD = 'platformPayments.method.tbankSbp';

    private const PERM_SBP = 'platformPayments.method.acquiringSbp';

    private const PERM_CARD = 'platformPayments.method.acquiringCard';

    public function up(): void
    {
        $now = Carbon::now();

        $groupId = DB::table('permission_groups')->where('slug', self::GROUP_SLUG)->value('id');
        if ($groupId === null) {
            return;
        }

        $old = DB::table('permissions')->where('name', self::PERM_SBP_OLD)->first();
        $current = DB::table('permissions')->where('name', self::PERM_SBP)->first();

        if ($old !== null && $current === null) {
            DB::table('permissions')->where('id', $old->id)->update([
                'name' => self::PERM_SBP,
                'description' => 'СБП · эквайринг (кошелёк и абонплата)',
                'sort_order' => 10,
                'is_visible' => 0,
                'updated_at' => $now,
            ]);
        } elseif ($current !== null) {
            DB::table('permissions')->where('id', $current->id)->update([
                'description' => 'СБП · эквайринг (кошелёк и абонплата)',
                'sort_order' => 10,
                'is_visible' => 0,
                'permission_group_id' => $groupId,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('permissions')->insert([
                'name' => self::PERM_SBP,
                'description' => 'СБП · эквайринг (кошелёк и абонплата)',
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $cardExists = DB::table('permissions')->where('name', self::PERM_CARD)->exists();
        if (! $cardExists) {
            DB::table('permissions')->insert([
                'name' => self::PERM_CARD,
                'description' => 'Карта · эквайринг (кошелёк и абонплата)',
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $cardId = DB::table('permissions')->where('name', self::PERM_CARD)->value('id');
        if ($cardId !== null && Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('permission_id', $cardId)->delete();
        }
        DB::table('permissions')->where('name', self::PERM_CARD)->delete();

        DB::table('permissions')->where('name', self::PERM_SBP)->update([
            'name' => self::PERM_SBP_OLD,
            'description' => 'T‑Bank СБП (кошелёк и абонплата)',
            'updated_at' => Carbon::now(),
        ]);
    }
};
