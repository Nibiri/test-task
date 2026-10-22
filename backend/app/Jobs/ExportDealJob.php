<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Deal;
use App\Models\DealExport;
use App\Sync\ExternalCrmClient;
use App\Sync\ExternalDealDTO;
use App\Sync\ExternalDealSyncService;
use App\Sync\FetchResult;
use App\Sync\SyncContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExportDealJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $exportId) {}

    public function handle(ExternalCrmClient $client, ExternalDealSyncService $sync): void
    {
        $export = DealExport::query()->find($this->exportId);
        if (! $export || ! in_array($export->status, ['accepted', 'running'], true)) {
            return;
        }

        $export->update([
            'status' => 'running',
            'attempt_count' => $export->attempt_count + 1,
            'started_at' => $export->started_at ?? now(),
            'lease_until' => now()->addSeconds((int) config('sync.refresh_lease_seconds')),
        ]);

        $max = (int) config('sync.max_attempts');
        $result = $client->create($export->idempotency_key, $export->payload, $export->scenario);

        if ($result->transportError !== null || $this->isTransient($result)) {
            $error = $result->transportError ?? 'HTTP '.$result->status;
            $export->update(['last_error' => $error]);
            if ($export->attempt_count < $max) {
                $delayMs = $this->delayMs($export->attempt_count, $result->retryAfterSeconds);
                self::dispatch($export->id)->delay(now()->addMilliseconds($delayMs));

                return;
            }
            $this->finishFailed($export, $error);

            return;
        }

        if ($result->status === 200) {
            DB::transaction(function () use ($sync, $export, $result): void {
                $deal = Deal::query()->whereKey($export->deal_id)->lockForUpdate()->firstOrFail();
                $incomingId = (string) $result->body['id'];
                if ($deal->external_id === null) {
                    $deal->external_id = $incomingId;
                    $deal->save();
                }
                $sync->apply(
                    ExternalDealDTO::fromArray($result->body),
                    new SyncContext('export'),
                );
            });
            $export->update([
                'status' => 'succeeded',
                'external_id' => (string) ($result->body['id'] ?? ''),
                'last_error' => null,
                'lease_until' => null,
                'finished_at' => now(),
            ]);

            return;
        }

        $this->finishFailed($export, 'HTTP '.$result->status);
    }

    private function isTransient(FetchResult $result): bool
    {
        return in_array($result->status, [429, 500, 502, 503, 504], true);
    }

    private function delayMs(int $attempt, ?int $retryAfterSeconds): int
    {
        $capMs = (int) config('sync.retry_after_cap_seconds') * 1000;
        $backoff = config('sync.backoff_ms');
        $base = (int) ($backoff[$attempt - 1] ?? 1000);
        $requested = $retryAfterSeconds !== null ? $retryAfterSeconds * 1000 : $base;

        return max(0, min($requested, $capMs));
    }

    private function finishFailed(DealExport $export, string $error): void
    {
        $export->update([
            'status' => 'failed',
            'last_error' => $error,
            'lease_until' => null,
            'finished_at' => now(),
        ]);
        Log::warning('External deal create failed.', [
            'export_id' => $export->id,
            'deal_id' => $export->deal_id,
            'error' => $error,
        ]);
    }
}
