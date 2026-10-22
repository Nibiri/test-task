<?php

declare(strict_types=1);

namespace App\Sync;

interface DispatchesSyncJobs
{
    public function dispatchWebhook(int $eventId): void;

    public function dispatchRefresh(int $operationId): void;

    public function dispatchExport(int $exportId): void;
}
