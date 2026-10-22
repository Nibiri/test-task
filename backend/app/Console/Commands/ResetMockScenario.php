<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ResetMockScenario extends Command
{
    protected $signature = 'mock:reset {externalId}';

    protected $description = 'Reset Redis counters for the mock API scenarios of one deal';

    public function handle(): int
    {
        $externalId = (string) $this->argument('externalId');
        foreach (['success', 'rate_limit', 'failure'] as $scenario) {
            Redis::del("mock:scenario:{$scenario}:deal:{$externalId}");
        }
        $this->info('Счётчики для '.$externalId.' сброшены.');

        return self::SUCCESS;
    }
}
