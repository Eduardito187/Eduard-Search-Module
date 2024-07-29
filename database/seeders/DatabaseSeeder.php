<?php

namespace Eduard\Search\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            TypeAttributes::class,
            ConditionsExcludes::class,
            SortingType::class,
        ]);
    }
}