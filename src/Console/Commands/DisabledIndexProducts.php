<?php

namespace Eduard\Search\Console\Commands;

use Carbon\Carbon;
use Eduard\Search\Models\AttributesRulesExclude;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Eduard\Search\Models\IndexProducts;
use Eduard\Search\Models\IndexCatalog;
use Eduard\Search\Models\ProductAttribute;
use Eduard\Search\Models\ProductIndex;

class DisabledIndexProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'disabledIndexProducts:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Desactiva productos no actualizados en las ultimas 24hrs.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $threshold = Carbon::now()->subHours(env('DISABLE_PRODUCT_UPDATE_AFTER') ?? 48);
        DB::table('index_products as ip')->join('product as p', 'ip.id_product', '=', 'p.id')->whereNotNull('ip.updated_at')->where('ip.updated_at', '<', $threshold)->update(['ip.status' => 0]);
        DB::table('index_products as ip')->join('product as p', 'ip.id_product', '=', 'p.id')->where('ip.updated_at', '>', $threshold)->update(['ip.status' => 1]);
        DB::table('product')->whereNotNull('updated_at')->where('updated_at', '<', $threshold)->update(['status' => 0]);

        $duplicates = IndexProducts::select('id_product', 'id_index_catalog', 'value', DB::raw('COUNT(*) as total'))->groupBy('id_product', 'id_index_catalog', 'value')->having('total', '>', 1)->get();

        foreach ($duplicates as $dup) {
            $toDelete = IndexProducts::where('id_product', $dup->id_product)->where('id_index_catalog', $dup->id_index_catalog)->where('value', $dup->value)->orderBy('id', 'asc')->skip(1)->take(PHP_INT_MAX)->get();

            foreach ($toDelete as $row) {
                $row->delete();
            }
        }

        $countItems = ProductIndex::select('id_index', DB::raw('COUNT(*) as total'))->groupBy('id_index')->pluck('total', 'id_index');

        foreach (IndexCatalog::all() as $index) {
            $index->count_product = $countItems[$index->id] ?? 0;
            $index->updated_at = date("Y-m-d H:i:s");
            $index->save();
        }

        $this->disabledProductsByCron();

        Log::info("Cron disabledIndexProducts ejecutado.");
        return Command::SUCCESS;
    }

    public function disabledProductsByCron()
    {
        $indexes = IndexCatalog::all();
        $rules = AttributesRulesExclude::all();

        $operadores = [
            1 => '>=',
            2 => '>',
            3 => '<=',
            4 => '<',
            5 => '=',
        ];

        foreach ($indexes as $index) {
            foreach ($rules as $rule) {
                $operador = $operadores[$rule->id_condition] ?? null;
                if (!$operador) {
                    continue;
                }

                $idProductsDisabled = ProductAttribute::select('product_attribute.id_product')
                    ->join('product_index', function ($join) use ($index) {
                        $join->on('product_attribute.id_product', '=', 'product_index.id_product')
                            ->on('product_attribute.id_index', '=', 'product_index.id_index');
                    })
                    ->where('product_attribute.id_attribute', $rule->id_attribute)
                    ->where('product_attribute.id_index', $index->id)
                    ->whereRaw("product_attribute.value {$operador} ?", [$rule->value])
                    ->distinct()->pluck('product_attribute.id_product')->toArray();

                if (!empty($idProductsDisabled)) {
                    ProductIndex::where('status', true)
                        ->where('id_index', $index->id)
                        ->whereIn('id_product', $idProductsDisabled)
                        ->update(['status' => 0]);

                    IndexProducts::where('status', true)
                        ->where('id_index_catalog', $index->id)
                        ->whereIn('id_product', $idProductsDisabled)
                        ->update(['status' => 0]);

                    Log::info("---PRODUCT DISABLED---");
                    Log::info("ID_INDEX => ".$index->id);
                    Log::info("LIST_PRODUCT => ".json_encode($idProductsDisabled));
                }
            }
        }
    }
}
