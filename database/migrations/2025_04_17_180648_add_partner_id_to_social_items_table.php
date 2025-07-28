<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPartnerIdToSocialItemsTable extends Migration
{
    public function up()
    {
        // ИЗМЕНЕНО: добавляем только если нет колонки partner_id
        if (! Schema::hasColumn('social_items', 'partner_id')) {
            Schema::table('social_items', function (Blueprint $table) {
                $table->unsignedBigInteger('partner_id')
                    ->nullable()
                    ->after('id');
            });
        }
    }

    public function down()
    {
        // ИЗМЕНЕНО: удаляем только если есть
        if (Schema::hasColumn('social_items', 'partner_id')) {
            Schema::table('social_items', function (Blueprint $table) {
                $table->dropColumn('partner_id');
            });
        }
    }
}
