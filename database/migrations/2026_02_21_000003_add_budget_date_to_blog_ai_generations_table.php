<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_ai_generations', function (Blueprint $table) {
            $table->date('budget_date')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('blog_ai_generations', function (Blueprint $table) {
            $table->dropIndex(['budget_date']);
            $table->dropColumn('budget_date');
        });
    }
};

