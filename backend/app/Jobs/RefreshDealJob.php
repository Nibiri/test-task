<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RefreshOperation;
use App\Sync\ExternalCrmClient;
use App\Sync\ExternalDealDTO;
use App\Sync\ExternalDealSyncService;
use App\Sync\FetchResult;
use App\Sync\SyncContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RefreshDealJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $operationId) {}

    public function handle(ExternalCrmClient $client, ExternalDealSyncService $sync): void
    {
        // Повтор идёт отдельным job с задержкой, воркер не блокируется usleep.
        $operation = RefreshOperation::query()->with('deal')->find($this->operationId);
        if (! $operation || ! in_array($operation->status, ['accepted', 'running'], true)) {
            return;
        }

        $operation->update([
            'status' => 'running',
            'attempt_count' => $operation->attempt_count + 1,
            'started_at' => $operation->started_at ?? now(),
            'lease_until' => now()->addSeconds((int) config('sync.refresh_lease_seconds')),
        ]);

        $max = (int) config('sync.max_attempts');
        $result = $client->fetch((string) $operation->deal->external_id, $operation->scenario);

        if ($result->transportError !== null || $this->isTransient($result)) {
            $error = $result->transportError ?? 'HTTP '.$result->status;
            $operation->update(['last_error' => $error]);
            if ($operation->attempt_count < $max) {
                $delayMs = $this->delayMs($operation->attempt_count, $result->retryAfterSeconds);
                self::dispatch($operation->id)->delay(now()->addMilliseconds($delayMs));

                return;
            }
            $this->finishFailed($operation, $error);

            return;
        }

        if ($result->status === 200) {
            $applied = $sync->apply(
                ExternalDealDTO::fromArray($result->body),
                new SyncContext('refresh', null, $operation->id),
            );
            $operation->update([
                'status' => 'succeeded',
                'outcome' => $applied->outcome,
                'outcome_detail' => $applied->detail(),
                'last_error' => null,
                'lease_until' => null,
                'finished_at' => now(),
            ]);

            return;
        }

        $this->finishFailed($operation, 'HTTP '.$result->status);
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

    private function finishFailed(RefreshOperation $operation, string $error): void
    {
        $operation->update([
            'status' => 'failed',
            'last_error' => $error,
            'lease_until' => null,
            'finished_at' => now(),
        ]);
        Log::warning('External refresh failed.', [
            'operation_id' => $operation->id,
            'deal_id' => $operation->deal_id,
            'error' => $error,
        ]);
    }
}
