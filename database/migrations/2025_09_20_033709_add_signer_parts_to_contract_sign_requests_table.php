<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contract_sign_requests', function (Blueprint $table) {
            // Раздельные поля ФИО подписанта
            $table->string('signer_lastname', 100)->nullable()->after('signer_name');
            $table->string('signer_firstname', 100)->nullable()->after('signer_lastname');
            $table->string('signer_middlename', 100)->nullable()->after('signer_firstname');

            // (опционально) индексы для удобства поиска
            $table->index(['signer_lastname', 'signer_firstname']);
        });
    }

    public function down(): void
    {
        Schema::table('contract_sign_requests', function (Blueprint $table) {
            $table->dropIndex(['signer_lastname', 'signer_firstname']);
            $table->dropColumn(['signer_lastname', 'signer_firstname', 'signer_middlename']);
        });
    }
};
