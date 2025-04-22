<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerRoleTable extends Migration
{
    public function up()
    {
        Schema::create('partner_role', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->primary(['partner_id', 'role_id']);
            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('partner_role');
    }
}
