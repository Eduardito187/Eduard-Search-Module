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
        Schema::create('search_clicks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('request_uuid', 36);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('product')->onDelete('cascade');
            $table->dateTime('clicked_at')->useCurrent();
            $table->integer('rank')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->string('raw_query_norm', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('referer', 255)->nullable();
            $table->binary('client_ip')->nullable();
            $table->string('ua', 255)->nullable();

            $table->unique(['request_uuid', 'product_id', 'clicked_at'], 'uniq_click');
            $table->index('request_uuid', 'idx_clicks_req');
            $table->index('product_id', 'idx_clicks_prod');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('search_clicks');
    }
};
