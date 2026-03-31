<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserFieldRoleTable extends Migration
{
    public function up()
    {
        Schema::create('user_field_role', function (Blueprint $table) {
            $table->unsignedBigInteger('user_field_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->primary(['user_field_id','role_id']);
            $table->foreign('user_field_id')->references('id')->on('user_fields')->onDelete('cascade');
            $table->foreign('role_id'       )->references('id')->on('roles'      )->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_field_role');
    }
}
