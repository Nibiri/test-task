<?php

declare(strict_types=1);

namespace App\Sync;

use App\Models\ExternalSnapshot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class MockExternalDealGateway
{
    /**
     * @return array{status: int, body: array<string, mixed>, retry_after: ?int}
     */
    public function respond(string $externalId, ?string $scenario): array
    {
        $scenario = $scenario ?: (string) config('sync.mock_scenario');
        $key = "mock:scenario:{$scenario}:deal:{$externalId}";

        if ($scenario === 'failure') {
            return ['status' => 500, 'body' => ['message' => 'external failure'], 'retry_after' => null];
        }

        if ($scenario === 'rate_limit') {
            $count = (int) Redis::incr($key);
            if ($count <= 2) {
                return ['status' => 429, 'body' => ['message' => 'rate limited'], 'retry_after' => 1];
            }
        } elseif ($scenario !== 'success') {
            return ['status' => 400, 'body' => ['message' => 'unknown scenario'], 'retry_after' => null];
        }

        $snapshot = ExternalSnapshot::query()->where('external_id', $externalId)->first();
        if (! $snapshot) {
            return ['status' => 404, 'body' => ['message' => 'not found'], 'retry_after' => null];
        }

        return [
            'status' => 200,
            'body' => $this->body($snapshot),
            'retry_after' => null,
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>, retry_after: ?int}
     */
    public function create(string $idempotencyKey, string $title, string $status, string $amount, ?string $scenario): array
    {
        $scenario = $scenario ?: (string) config('sync.mock_scenario');
        $limited = $this->scenarioGate($scenario, "mock:scenario:{$scenario}:create:{$idempotencyKey}");
        if ($limited !== null) {
            return $limited;
        }

        $existing = ExternalSnapshot::query()->where('client_reference', $idempotencyKey)->first();
        if ($existing) {
            return ['status' => 200, 'body' => $this->body($existing), 'retry_after' => null];
        }

        try {
            $snapshot = ExternalSnapshot::query()->create([
                'external_id' => (string) DB::scalar("select nextval('mock_external_deal_ids')"),
                'client_reference' => $idempotencyKey,
                'version' => 1,
                'updated_at' => now(),
                'title' => $title,
                'status' => $status,
                'amount' => $amount,
            ]);
        } catch (UniqueConstraintViolationException) {
            $snapshot = ExternalSnapshot::query()->where('client_reference', $idempotencyKey)->firstOrFail();
        }

        return ['status' => 200, 'body' => $this->body($snapshot), 'retry_after' => null];
    }

    /**
     * @return array{status: int, body: array<string, mixed>, retry_after: ?int}|null
     */
    private function scenarioGate(string $scenario, string $key): ?array
    {
        if ($scenario === 'failure') {
            return ['status' => 500, 'body' => ['message' => 'external failure'], 'retry_after' => null];
        }

        if ($scenario === 'rate_limit') {
            $count = (int) Redis::incr($key);
            if ($count <= 2) {
                return ['status' => 429, 'body' => ['message' => 'rate limited'], 'retry_after' => 1];
            }

            return null;
        }

        if ($scenario !== 'success') {
            return ['status' => 400, 'body' => ['message' => 'unknown scenario'], 'retry_after' => null];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(ExternalSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->external_id,
            'version' => $snapshot->version,
            'updated_at' => $snapshot->updated_at?->toIso8601String(),
            'title' => $snapshot->title,
            'status' => $snapshot->status,
            'amount' => (float) $snapshot->amount,
        ];
    }
}
