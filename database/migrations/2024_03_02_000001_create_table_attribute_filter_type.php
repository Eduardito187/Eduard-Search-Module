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
        Schema::create('attribute_filter_type', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_client')->nullable();
            $table->foreign('id_client')->references('id')->on('client')->onDelete('cascade');
            $table->unsignedBigInteger('id_attribute')->nullable();
            $table->foreign('id_attribute')->references('id')->on('attributes')->onDelete('cascade');
            $table->string('type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attribute_filter_type', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_attribute');
            $table->dropConstrainedForeignId('id_client');
        });
        Schema::dropIfExists('attribute_filter_type');
    }
};