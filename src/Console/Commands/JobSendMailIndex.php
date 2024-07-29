<?php

namespace Eduard\Search\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class JobSendMailIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobSendMailIndex:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute proccess jobs.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        exec("php artisan queue:work database --queue=send_mail_index --stop-when-empty --max-jobs=20");
        return Command::SUCCESS;
    }
}