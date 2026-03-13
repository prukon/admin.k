<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->unsignedTinyInteger('taxation_system')
                ->nullable()
                ->after('tax_id')
                ->comment('0=OSN,1=USN income,2=USN income-expense,3=ENVD,4=ESHN,5=Patent');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('taxation_system');
        });
    }
};