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
        Schema::create('history_query_search', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_client')->nullable();
            $table->foreign('id_client')->references('id')->on('client')->onDelete('cascade');
            $table->unsignedBigInteger('id_index')->nullable();
            $table->foreign('id_index')->references('id')->on('index_catalog')->onDelete('cascade');
            $table->string('customer_uuid');
            $table->string('query');
            $table->double('time_execution');
            $table->string('code');
            $table->integer('count_items');
            $table->timestamp('created_at');
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('history_query_search', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_client');
            $table->dropConstrainedForeignId('id_index');
        });
        Schema::dropIfExists('history_query_search');
    }
};