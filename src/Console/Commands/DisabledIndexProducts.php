<?php

namespace Eduard\Search\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        DB::table('index_products as ip')->join('product as p', 'ip.id_product', '=', 'p.id')->whereNotNull('p.updated_at')->where('p.updated_at', '<', $threshold)->update(['ip.status' => 0]);
        DB::table('product')->whereNotNull('updated_at')->where('updated_at', '<', $threshold)->update(['status' => 0]);
        Log::info("Cron disabledIndexProducts ejecutado.");
        return Command::SUCCESS;
    }
}
