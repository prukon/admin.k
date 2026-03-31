<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPartnerIdToUserFieldsTable extends Migration
{
    public function up()
    {
        Schema::table('user_fields', function (Blueprint $table) {
            // Добавляем поле partner_id. Здесь сделано поле nullable,
            // чтобы существующие записи не нарушались.
            $table->unsignedBigInteger('partner_id')->nullable()->after('slug');
        });
    }

    public function down()
    {
        Schema::table('user_fields', function (Blueprint $table) {
            $table->dropColumn('partner_id');
        });
    }
}
