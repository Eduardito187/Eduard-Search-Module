<?php

namespace Eduard\Search\Database\Seeders;

use Illuminate\Database\Seeder;
use Eduard\Search\Models\TypeAttribute;
use Illuminate\Support\Facades\DB;

class TypeAttributes extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (TypeAttribute::count() == 0) {
            DB::table("type_attribute")->insert([
                "id" => 1,
                "name" => "Integer",
                "type" => "integer",
                "sortable" => false,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => null
            ]);
            DB::table("type_attribute")->insert([
                "id" => 2,
                "name" => "String",
                "type" => "string",
                "sortable" => false,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => null
            ]);
            DB::table("type_attribute")->insert([
                "id" => 3,
                "name" => "Decimal",
                "type" => "decimal",
                "sortable" => false,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => null
            ]);
        }
    }
}