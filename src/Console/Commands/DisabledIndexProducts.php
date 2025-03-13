<?php

namespace Eduard\Search\Console\Commands;

use Eduard\Search\Models\Attributes;
use Eduard\Account\Models\Client;
use Eduard\Search\Models\IndexProducts;
use Eduard\Search\Models\Product;
use Eduard\Search\Models\ProductAttribute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
        $fechaLimite = Carbon::now()->subHours(24);
        IndexProducts::where('status', true)->where('updated_at', '<', $fechaLimite)->update(['status' => false]);
        $allClient = Client::all();

        foreach ($allClient as $key => $client) {
            $price = Attributes::where('code', 'price')->where('id_client', $client->id)->first();

            if (!$price) {
                foreach ($client->indexes as $key => $index) {
                    Product::where('status', true)->where('id_client', $client->id)->update(['status' => false]);
                }
            } else {
                foreach ($client->indexes as $key => $index) {
                    $filteredProducts = ProductAttribute::where('id_attribute', $price->id)
                    ->where(function ($query) {
                        $query->where('value', null)
                            ->orWhere('value', '<', 1);
                    })->pluck('id_product')->toArray();
                    Product::where('status', true)->whereIn('id', $filteredProducts)->update(['status' => false]);

                    $productsWithoutAttributes = Product::whereNotIn('id', function ($query) {
                        $query->select('id_product')->from('product_attribute')->where('id_attribute', 1)
                            ->where(function ($subquery) {
                                $subquery->where('value', null)
                                    ->orWhere('value', '<', 1);
                            });
                    })->pluck('id')->toArray();
                    Product::where('status', true)->whereIn('id', $productsWithoutAttributes)->update(['status' => false]);
                }
            }
        }


        Log::channel('disabledIndexProducts')->info("Cron disabledIndexProducts ejecutado.");
        return Command::SUCCESS;
    }
}