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
        Schema::table('index_products', function (Blueprint $table) {
            $table->fullText('value', 'index_products_value_fulltext');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('index_products', function (Blueprint $table) {
            $table->dropFullText('index_products_value_fulltext');
        });
    }
};
