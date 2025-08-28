<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->string('website')->nullable()->after('phone');
        });
    }
    public function down()
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropColumn('website');
        });
    }

};
