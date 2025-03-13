<?php

namespace Eduard\Search\Console\Commands;

use Eduard\Search\Models\AttributesRulesExclude;
use Eduard\Search\Models\Product;
use Eduard\Search\Models\ProductAttribute;
use Eduard\Search\Models\ProductIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunnerRulesExclude extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'runnerRulesExclude:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Desactiva de los indices los productos que no cumplan con las reglas de exclusion.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /*
        $allRulesExcludes = AttributesRulesExclude::all();

        foreach ($allRulesExcludes as $rule) {
            foreach ($rule->client->indexes as $index) {
                $idProductsDisabled = [];

                switch ($rule->id_condition) {
                    case 1:
                        $idProductsDisabled = ProductAttribute::join('product_index', function ($join) use ($rule, $index) {
                            $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                                 ->on('product_attribute.id_index', '=', 'product_index.id_index');
                        })
                        ->where('product_attribute.id_attribute', $rule->id_attribute)
                        ->where('product_attribute.value', '>=', $rule->value)
                        ->where('product_attribute.id_index', $index->id)
                        ->where('product_index.status', 1)
                        ->pluck('product_attribute.id_product')->toArray();
                    case 2:
                        $idProductsDisabled = ProductAttribute::join('product_index', function ($join) use ($rule, $index) {
                            $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                                 ->on('product_attribute.id_index', '=', 'product_index.id_index');
                        })
                        ->where('product_attribute.id_attribute', $rule->id_attribute)
                        ->where('product_attribute.value', '>', $rule->value)
                        ->where('product_attribute.id_index', $index->id)
                        ->where('product_index.status', 1)
                        ->pluck('product_attribute.id_product')->toArray();
                    case 3:
                        $idProductsDisabled = ProductAttribute::join('product_index', function ($join) use ($rule, $index) {
                            $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                                 ->on('product_attribute.id_index', '=', 'product_index.id_index');
                        })
                        ->where('product_attribute.id_attribute', $rule->id_attribute)
                        ->where('product_attribute.value', '<=', $rule->value)
                        ->where('product_attribute.id_index', $index->id)
                        ->where('product_index.status', 1)
                        ->pluck('product_attribute.id_product')->toArray();
                    case 4:
                        $idProductsDisabled = ProductAttribute::join('product_index', function ($join) use ($rule, $index) {
                            $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                                 ->on('product_attribute.id_index', '=', 'product_index.id_index');
                        })
                        ->where('product_attribute.id_attribute', $rule->id_attribute)
                        ->where('product_attribute.value', '<', $rule->value)
                        ->where('product_attribute.id_index', $index->id)
                        ->where('product_index.status', 1)
                        ->pluck('product_attribute.id_product')->toArray();
                    case 5:
                        $idProductsDisabled = ProductAttribute::join('product_index', function ($join) use ($rule, $index) {
                            $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                                 ->on('product_attribute.id_index', '=', 'product_index.id_index');
                        })
                        ->where('product_attribute.id_attribute', $rule->id_attribute)
                        ->where('product_attribute.value', '=', $rule->value)
                        ->where('product_attribute.id_index', $index->id)
                        ->where('product_index.status', 1)
                        ->pluck('product_attribute.id_product')->toArray();
                }

                Log::channel('runnerRulesExclude')->info("condition => ".$rule->id_condition);
                $productsWithoutAttributes = Product::leftJoin('product_attribute', 'product.id', '=', 'product_attribute.id_product')->where('product_attribute.id_attribute', $rule->id_attribute)->where('product_attribute.id_index', $index->id)->whereNull('product_attribute.id_product')->pluck('product.id')->toArray();
                ProductIndex::where('status', true)->whereIn('id_product', array_merge($idProductsDisabled, $productsWithoutAttributes))->update(['status' => false]);
                Log::channel('runnerRulesExclude')->info("productsId => ".json_encode(array_merge($idProductsDisabled, $productsWithoutAttributes)));
            }
        }
        */

        Log::channel('runnerRulesExclude')->info("Cron runnerRulesExclude ejecutado.");
        return Command::SUCCESS;
    }
}