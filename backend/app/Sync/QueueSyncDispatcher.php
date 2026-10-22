<?php

declare(strict_types=1);

namespace App\Sync;

use App\Jobs\ExportDealJob;
use App\Jobs\ProcessWebhookEvent;
use App\Jobs\RefreshDealJob;

final class QueueSyncDispatcher implements DispatchesSyncJobs
{
    public function dispatchWebhook(int $eventId): void
    {
        ProcessWebhookEvent::dispatch($eventId);
    }

    public function dispatchRefresh(int $operationId): void
    {
        RefreshDealJob::dispatch($operationId);
    }

    public function dispatchExport(int $exportId): void
    {
        ExportDealJob::dispatch($exportId);
    }
}
