<?php

declare(strict_types=1);

namespace App\Sync;

use App\Models\Deal;
use App\Models\DealFieldOverride;
use App\Models\FieldConflictLog;
use App\Support\FieldValue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class DealFieldEditor
{
    public function create(array $input): Deal
    {
        return Deal::query()->create([
            'external_id' => $input['external_id'] ?? null,
            'title' => $input['title'],
            'status' => $input['status'],
            'amount' => FieldValue::canonical('amount', $input['amount']),
        ]);
    }

    public function update(Deal $deal, array $input): Deal
    {
        return DB::transaction(function () use ($deal, $input) {
            $locked = Deal::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();

            foreach (FieldValue::NAMES as $field) {
                if (! array_key_exists($field, $input)) {
                    continue;
                }
                $this->writeField($locked, $field, FieldValue::canonical($field, $input[$field]));
            }

            $locked->save();

            return $locked->fresh()->load(['overrides', 'latestRefresh']);
        });
    }

    public function resolve(Deal $deal, string $field, string $action, int $expectedVersion): Deal
    {
        return DB::transaction(function () use ($deal, $field, $action, $expectedVersion) {
            $locked = Deal::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            if ($locked->external_version === null || (int) $locked->external_version !== $expectedVersion) {
                throw new StaleResolution('External version changed.');
            }

            $override = DealFieldOverride::query()
                ->where('deal_id', $locked->id)
                ->where('field_name', $field)
                ->lockForUpdate()
                ->first();

            if (! $override) {
                throw (new ModelNotFoundException)->setModel(DealFieldOverride::class);
            }

            $remote = FieldValue::canonical($field, $locked->getAttribute('external_'.$field));

            if ($action === 'accept_external') {
                FieldConflictLog::query()->create([
                    'deal_id' => $locked->id,
                    'field_name' => $field,
                    'state' => FieldConflictLog::RESOLVED_EXTERNAL,
                    'external_version' => $locked->external_version,
                    'base_value' => $override->base_value,
                    'local_value' => $override->local_value,
                    'remote_value' => $remote,
                    'created_at' => now(),
                ]);
                $override->delete();
            } else {
                $override->base_value = $remote;
                $override->is_conflicted = false;
                $override->save();
                FieldConflictLog::query()->create([
                    'deal_id' => $locked->id,
                    'field_name' => $field,
                    'state' => FieldConflictLog::RESOLVED_LOCAL,
                    'external_version' => $locked->external_version,
                    'base_value' => $remote,
                    'local_value' => $override->local_value,
                    'remote_value' => $remote,
                    'created_at' => now(),
                ]);
            }

            $locked->save();

            return $locked->fresh()->load(['overrides', 'latestRefresh']);
        });
    }

    private function writeField(Deal $deal, string $field, string $value): void
    {
        if ($deal->external_version === null) {
            $deal->setAttribute($field, $value);

            return;
        }

        $remote = FieldValue::canonical($field, $deal->getAttribute('external_'.$field));
        $override = DealFieldOverride::query()
            ->where('deal_id', $deal->id)
            ->where('field_name', $field)
            ->lockForUpdate()
            ->first();

        if ($value === $remote) {
            if ($override) {
                FieldConflictLog::query()->create([
                    'deal_id' => $deal->id,
                    'field_name' => $field,
                    'state' => FieldConflictLog::CONVERGED,
                    'external_version' => $deal->external_version,
                    'base_value' => $override->base_value,
                    'local_value' => $value,
                    'remote_value' => $remote,
                    'created_at' => now(),
                ]);
                $override->delete();
            }

            return;
        }

        if ($override) {
            $override->local_value = $value;
            $override->save();

            return;
        }

        DealFieldOverride::query()->create([
            'deal_id' => $deal->id,
            'field_name' => $field,
            'base_value' => $remote,
            'local_value' => $value,
            'is_conflicted' => false,
        ]);
    }
}
