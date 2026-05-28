<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_individual_traits')->nullable()->after('parent_id');
            $table->boolean('is_on_medical_register')->nullable()->after('is_individual_traits');
            $table->boolean('is_with_disability')->nullable()->after('is_on_medical_register');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_individual_traits',
                'is_on_medical_register',
                'is_with_disability',
            ]);
        });
    }
};
