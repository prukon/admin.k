<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() 
    { 
        // ИЗМЕНЕНИЕ: защита от отсутствующей таблицы
        if (!Schema::hasTable('contact_submissions')) {
            return;
        }

        Schema::table('contact_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_submissions', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }

            if (!Schema::hasColumn('contact_submissions', 'status')) {
                $table->enum('status', ['new', 'processing', 'sale', 'rejected', 'spam'])
                    ->default('new')
                    ->after('message');
            }

            if (!Schema::hasColumn('contact_submissions', 'comment')) {
                $table->text('comment')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // ИЗМЕНЕНИЕ: защита от отсутствующей таблицы
        if (!Schema::hasTable('contact_submissions')) {
            return;
        }

        Schema::table('contact_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('contact_submissions', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('contact_submissions', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('contact_submissions', 'comment')) {
                $table->dropColumn('comment');
            }
        });
    }
};
