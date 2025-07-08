<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestSchedulerCommand extends Command
{
    protected $signature = 'test:scheduler';
    protected $description = 'Test if scheduler is working';

    public function handle()
    {
        \Log::info('✅ test:scheduler command ran at ' . now());
    }
}
