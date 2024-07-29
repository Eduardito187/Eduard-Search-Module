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
        Schema::create('index_configuration', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_index_catalog')->nullable();
            $table->foreign('id_index_catalog')->references('id')->on('index_catalog')->onDelete('cascade');
            $table->integer('limit_product_feed');
            $table->integer('page_limit');
            $table->integer('limit_pagination');
            $table->string('api_key');
            $table->boolean('status');
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
        Schema::dropIfExists('index_configuration', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_index_catalog');
        });
        Schema::dropIfExists('index_configuration');
    }
};