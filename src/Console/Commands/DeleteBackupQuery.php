<?php

namespace Eduard\Search\Console\Commands;

use Eduard\Search\Models\BackupQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DeleteBackupQuery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deleteBackupQuery:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina la cache de las busquedas de 10min.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fechaLimite = Carbon::now()->subMinutes(10);
        BackupQuery::where('created_at', '<', $fechaLimite)->delete();

        Log::channel('deleteBackupQuery')->info("Cron deleteBackupQuery ejecutado.");
        return Command::SUCCESS;
    }
}