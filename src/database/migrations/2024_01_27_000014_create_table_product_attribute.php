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
        Schema::create('product_attribute', function (Blueprint $table) {
            $table->unsignedBigInteger('id_product')->nullable();
            $table->foreign('id_product')->references('id')->on('product')->onDelete('cascade');
            $table->unsignedBigInteger('id_attribute')->nullable();
            $table->foreign('id_attribute')->references('id')->on('attributes')->onDelete('cascade');
            $table->unsignedBigInteger('id_index')->nullable();
            $table->foreign('id_index')->references('id')->on('index_catalog')->onDelete('cascade');
            $table->longText('value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_attribute', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_product');
            $table->dropConstrainedForeignId('id_attribute');
            $table->dropConstrainedForeignId('id_index');
        });
        Schema::dropIfExists('product_attribute');
    }
};