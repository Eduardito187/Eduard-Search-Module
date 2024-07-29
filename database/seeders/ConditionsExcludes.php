<?php

namespace Eduard\Search\Database\Seeders;

use Illuminate\Database\Seeder;
use Eduard\Search\Models\ConditionsExcludes as ModelConditionsExcludes;
use Illuminate\Support\Facades\DB;

class ConditionsExcludes extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (ModelConditionsExcludes::count() == 0) {
            DB::table("condition_exclude")->insert([
                "id" => 1,
                "name" => "Exclude Example 1",
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => null
            ]);
            DB::table("condition_exclude")->insert([
                "id" => 2,
                "name" => "Exclude Example 2",
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => null
            ]);
        }
    }
}