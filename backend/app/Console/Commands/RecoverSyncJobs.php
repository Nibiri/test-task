<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RefreshOperation;
use App\Models\WebhookEvent;
use App\Sync\DispatchesSyncJobs;
use Illuminate\Console\Command;
use Throwable;

class RecoverSyncJobs extends Command
{
    protected $signature = 'sync:recover';

    protected $description = 'Redispatch webhook events and refresh operations that were persisted but not finished';

    public function handle(DispatchesSyncJobs $jobs): int
    {
        WebhookEvent::query()
            ->where('status', 'processing')
            ->whereNotNull('lease_until')
            ->where('lease_until', '<=', now())
            ->update([
                'status' => 'received',
                'lease_until' => null,
                'dispatch_after' => now(),
            ]);

        RefreshOperation::query()
            ->where('status', 'running')
            ->whereNotNull('lease_until')
            ->where('lease_until', '<=', now())
            ->update([
                'status' => 'failed',
                'last_error' => 'Обработка прервана и не возобновилась.',
                'finished_at' => now(),
                'lease_until' => null,
            ]);

        $events = WebhookEvent::query()
            ->where('status', 'received')
            ->where(function ($query): void {
                $query->whereNull('dispatch_after')->orWhere('dispatch_after', '<=', now());
            })
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($events as $event) {
            try {
                $jobs->dispatchWebhook($event->id);
                $event->update([
                    'dispatch_after' => now()->addSeconds((int) config('sync.dispatch_grace_seconds')),
                ]);
            } catch (Throwable $exception) {
                $this->error($event->event_id.': '.$exception->getMessage());
            }
        }

        $operations = RefreshOperation::query()
            ->where('status', 'accepted')
            ->where(function ($query): void {
                $query->whereNull('dispatch_after')->orWhere('dispatch_after', '<=', now());
            })
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($operations as $operation) {
            try {
                $jobs->dispatchRefresh($operation->id);
                $operation->update([
                    'dispatch_after' => now()->addSeconds((int) config('sync.dispatch_grace_seconds')),
                ]);
            } catch (Throwable $exception) {
                $this->error('refresh '.$operation->id.': '.$exception->getMessage());
            }
        }

        \App\Models\DealExport::query()
            ->where('status', 'running')
            ->whereNotNull('lease_until')
            ->where('lease_until', '<=', now())
            ->update([
                'status' => 'failed',
                'last_error' => 'Отправка в CRM прервана и не возобновилась.',
                'finished_at' => now(),
                'lease_until' => null,
            ]);

        $exports = \App\Models\DealExport::query()
            ->where('status', 'accepted')
            ->where(function ($query): void {
                $query->whereNull('dispatch_after')->orWhere('dispatch_after', '<=', now());
            })
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($exports as $export) {
            try {
                $jobs->dispatchExport($export->id);
                $export->update([
                    'dispatch_after' => now()->addSeconds((int) config('sync.dispatch_grace_seconds')),
                ]);
            } catch (Throwable $exception) {
                $this->error('export '.$export->id.': '.$exception->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
