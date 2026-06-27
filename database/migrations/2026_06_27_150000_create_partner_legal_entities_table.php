<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_legal_entities')) {
            return;
        }

        Schema::create('partner_legal_entities', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');

            $table->enum('business_type', ['OOO', 'IP', 'ANO', 'NKO']);

            $table->string('title');
            $table->string('organization_name')->nullable();

            $table->string('tax_id', 12)->nullable();
            $table->string('kpp', 9)->nullable();
            $table->string('registration_number', 20)->nullable();

            $table->string('city', 100)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('address')->nullable();

            $table->json('ceo')->nullable();

            $table->string('bank_name')->nullable();
            $table->string('bank_bik', 20)->nullable();
            $table->string('bank_account', 20)->nullable();
            $table->text('sm_details_template')->nullable();

            /** ShopCode / PartnerId T‑Bank (sm-register). */
            $table->string('tinkoff_shop_code')->nullable();
            $table->string('sm_register_status')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->unsignedInteger('bank_details_version')->nullable();
            $table->timestamp('bank_details_last_updated_at')->nullable();
            $table->timestamp('registration_verified_at')->nullable();

            $table->unsignedTinyInteger('taxation_system')->nullable();
            $table->unsignedTinyInteger('vat')->nullable();

            $table->string('sms_name', 14)->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['partner_id', 'is_enabled'], 'ple_partner_enabled_idx');
            $table->index(['partner_id', 'is_default'], 'ple_partner_default_idx');
            $table->unique(['partner_id', 'tax_id'], 'ple_partner_tax_id_unique');
            $table->unique('tinkoff_shop_code', 'ple_tinkoff_shop_code_unique');

            $table->foreign('partner_id', 'ple_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_legal_entities');
    }
};
