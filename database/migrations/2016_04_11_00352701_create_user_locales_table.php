<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUserLocalesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public
    function up()
    {
        Schema::create('ltm_user_locales', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_email', 255);
            $table->text('locales')->nullable();
            $table->index(['user_email'], 'ix_ltm_user_locales_user_email');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public
    function down()
    {
        Schema::drop('ltm_user_locales');
    }
}
