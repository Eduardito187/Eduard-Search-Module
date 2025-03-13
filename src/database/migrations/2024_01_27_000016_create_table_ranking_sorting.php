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
        //attribute_order
        Schema::create('ranking_sorting', function (Blueprint $table) {
            $table->unsignedBigInteger('id_attribute')->nullable();
            $table->foreign('id_attribute')->references('id')->on('attributes')->onDelete('cascade');
            $table->unsignedBigInteger('id_index')->nullable();
            $table->foreign('id_index')->references('id')->on('index_catalog')->onDelete('cascade');
            $table->unsignedBigInteger('id_sort_type')->nullable();
            $table->foreign('id_sort_type')->references('id')->on('sorting_type')->onDelete('cascade');
            $table->integer('order');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ranking_sorting', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_attribute');
            $table->dropConstrainedForeignId('id_index');
            $table->dropConstrainedForeignId('id_sort_type');
        });
        Schema::dropIfExists('ranking_sorting');
    }
};