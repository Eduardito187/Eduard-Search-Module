<?php

namespace Eduard\Search\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Eduard\Search\Models\IndexProducts;
use Eduard\Search\Models\IndexCatalog;
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
            $index->save();
        }

        Log::info("Cron disabledIndexProducts ejecutado.");
        return Command::SUCCESS;
    }
}
