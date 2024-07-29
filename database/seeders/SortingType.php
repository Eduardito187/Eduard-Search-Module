<?php

namespace Eduard\Search\Database\Seeders;

use Illuminate\Database\Seeder;
use Eduard\Search\Models\SortingType as ModelSortingType;
use Illuminate\Support\Facades\DB;

class SortingType extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (ModelSortingType::count() == 0) {
            DB::table("sorting_type")->insert([
                "id" => 1,
                "name" => "ASC",
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => null
            ]);
            DB::table("sorting_type")->insert([
                "id" => 2,
                "name" => "DESC",
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => null
            ]);
        }
    }
}