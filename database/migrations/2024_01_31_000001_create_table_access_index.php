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
        Schema::create('access_index', function (Blueprint $table) {
            $table->unsignedBigInteger('id_autorization_token')->nullable();
            $table->foreign('id_autorization_token')->references('id')->on('autorization_token')->onDelete('cascade');
            $table->unsignedBigInteger('id_index')->nullable();
            $table->foreign('id_index')->references('id')->on('index_catalog')->onDelete('cascade');
            $table->unsignedBigInteger('id_client')->nullable();
            $table->foreign('id_client')->references('id')->on('client')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('access_index', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_autorization_token');
            $table->dropConstrainedForeignId('id_index');
            $table->dropConstrainedForeignId('id_client');
        });
        Schema::dropIfExists('access_index');
    }
};