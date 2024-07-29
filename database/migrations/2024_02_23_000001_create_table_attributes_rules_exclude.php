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
        Schema::create('attributes_rules_exclude', function (Blueprint $table) {
            $table->unsignedBigInteger('id_client')->nullable();
            $table->foreign('id_client')->references('id')->on('client')->onDelete('cascade');
            $table->unsignedBigInteger('id_attribute')->nullable();
            $table->foreign('id_attribute')->references('id')->on('attributes')->onDelete('cascade');
            $table->unsignedBigInteger('id_condition')->nullable();
            $table->foreign('id_condition')->references('id')->on('conditions_excludes')->onDelete('cascade');
            $table->integer('value');
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
        Schema::dropIfExists('attributes_rules_exclude', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_client');
            $table->dropConstrainedForeignId('id_attribute');
            $table->dropConstrainedForeignId('id_condition');
        });
        Schema::dropIfExists('attributes_rules_exclude');
    }
};