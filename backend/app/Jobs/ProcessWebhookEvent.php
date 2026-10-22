<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Sync\ExternalDealDTO;
use App\Sync\ExternalDealSyncService;
use App\Sync\SyncContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $eventId) {}

    public function handle(ExternalDealSyncService $sync): void
    {
        $claimed = WebhookEvent::query()
            ->whereKey($this->eventId)
            ->where('status', 'received')
            ->update([
                'status' => 'processing',
                'lease_until' => now()->addSeconds((int) config('sync.webhook_lease_seconds')),
                'attempts' => \Illuminate\Support\Facades\DB::raw('attempts + 1'),
            ]);

        if ($claimed === 0) {
            return;
        }

        $event = WebhookEvent::query()->findOrFail($this->eventId);

        try {
            $result = $sync->apply(
                ExternalDealDTO::fromArray($event->payload['deal']),
                new SyncContext('webhook', $event->id),
            );
            $event->update([
                'status' => $result->outcome === 'applied' ? 'applied' : 'skipped',
                'result_reason' => $result->reason,
                'result_detail' => $result->detail(),
                'lease_until' => null,
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $event->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'lease_until' => null,
                'processed_at' => now(),
            ]);
            Log::error('Webhook processing failed.', [
                'event_id' => $event->event_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
