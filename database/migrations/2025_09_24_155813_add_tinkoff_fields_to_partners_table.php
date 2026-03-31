<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('partners', function (Blueprint $t) {
            $t->string('tinkoff_partner_id')->nullable()->index(); // shopCode
            $t->string('sm_register_status')->nullable();
            $t->integer('bank_details_version')->nullable();
            $t->timestamp('bank_details_last_updated_at')->nullable();
            $t->text('sm_details_template')->nullable(); // шаблон назначения, если нужно хранить локально
        });
    }
    public function down(): void {
        Schema::table('partners', function (Blueprint $t) {
            $t->dropColumn([
                'tinkoff_partner_id','sm_register_status','bank_details_version',
                'bank_details_last_updated_at','sm_details_template'
            ]);
        });
    }
};
