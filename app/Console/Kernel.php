<?php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
    \App\Console\Commands\SyncGoalServeMatches::class,
    \App\Console\Commands\ScrapeCricbuzzMatches::class,
];

    protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        \Log::info('✅ Scheduler closure ran at ' . now());
    })->everyMinute();
}



    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
