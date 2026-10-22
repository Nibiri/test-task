<?php

declare(strict_types=1);

namespace App\Sync;

use App\Models\Deal;
use App\Models\DealFieldOverride;
use App\Models\FieldConflictLog;
use App\Models\SourceInconsistency;
use App\Support\FieldValue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class ExternalDealSyncService
{
    public function apply(ExternalDealDTO $incoming, SyncContext $context): SyncResult
    {
        return DB::transaction(function () use ($incoming, $context) {
            [$deal, $created] = $this->lockOrCreate($incoming);
            if ($created) {
                return new SyncResult('applied', 'created', $deal->id, accepted: FieldValue::NAMES);
            }

            if ($deal->external_version !== null && $incoming->version < $deal->external_version) {
                return new SyncResult('skipped', 'stale_version', $deal->id);
            }

            if ($deal->external_version !== null && $incoming->version === $deal->external_version) {
                if ($this->snapshotDiffers($deal, $incoming)) {
                    $this->recordInconsistency($deal, $incoming, $context);

                    return new SyncResult('inconsistency', 'source_inconsistency', $deal->id);
                }

                return new SyncResult('skipped', 'same_version', $deal->id);
            }

            return $this->merge($deal, $incoming);
        });
    }

    /**
     * @return array{0: Deal, 1: bool}
     */
    private function lockOrCreate(ExternalDealDTO $incoming): array
    {
        $existing = Deal::query()->where('external_id', $incoming->id)->lockForUpdate()->first();
        if ($existing) {
            return [$existing, false];
        }

        try {
            $deal = DB::transaction(fn () => Deal::query()->create($this->snapshotAttributes($incoming)));

            return [$deal, true];
        } catch (UniqueConstraintViolationException) {
            $deal = Deal::query()->where('external_id', $incoming->id)->lockForUpdate()->first();
            if (! $deal) {
                throw new \RuntimeException('Deal row disappeared after a unique conflict.');
            }

            return [$deal, false];
        }
    }

    private function merge(Deal $deal, ExternalDealDTO $incoming): SyncResult
    {
        $overrides = DealFieldOverride::query()
            ->where('deal_id', $deal->id)
            ->lockForUpdate()
            ->get()
            ->keyBy('field_name');

        $firstSnapshot = $deal->external_version === null;
        $preserved = [];
        $conflicted = [];
        $converged = [];
        $accepted = [];

        foreach (FieldValue::NAMES as $field) {
            $remote = FieldValue::canonical($field, $incoming->field($field));
            $override = $overrides->get($field);

            if ($override === null && $firstSnapshot) {
                $manual = $deal->getAttribute($field);
                if ($manual !== null && FieldValue::canonical($field, $manual) !== $remote) {
                    $override = DealFieldOverride::query()->create([
                        'deal_id' => $deal->id,
                        'field_name' => $field,
                        'base_value' => $remote,
                        'local_value' => FieldValue::canonical($field, $manual),
                        'is_conflicted' => true,
                    ]);
                    $this->log($deal, $override, FieldConflictLog::DETECTED, $incoming->version, $remote);
                    $conflicted[] = $field;
                } else {
                    $accepted[] = $field;
                }
                $deal->setAttribute($field, null);
                $deal->setAttribute('external_'.$field, $remote);
                continue;
            }

            if ($override === null) {
                $deal->setAttribute($field, null);
                $deal->setAttribute('external_'.$field, $remote);
                $accepted[] = $field;
                continue;
            }

            $base = FieldValue::canonical($field, $override->base_value);
            $local = FieldValue::canonical($field, $override->local_value);
            $deal->setAttribute('external_'.$field, $remote);
            $deal->setAttribute($field, null);

            if ($remote === $local) {
                $this->log($deal, $override, FieldConflictLog::CONVERGED, $incoming->version, $remote);
                $override->delete();
                $converged[] = $field;
                continue;
            }

            if ($remote !== $base && ! $override->is_conflicted) {
                $override->is_conflicted = true;
                $override->save();
                $this->log($deal, $override, FieldConflictLog::DETECTED, $incoming->version, $remote);
            }

            if ($override->is_conflicted) {
                $conflicted[] = $field;
            } else {
                $preserved[] = $field;
            }
        }

        $deal->external_version = $incoming->version;
        $deal->external_updated_at = $incoming->updatedAt;
        $deal->save();

        return new SyncResult('applied', 'merged', $deal->id, $preserved, $conflicted, $converged, $accepted);
    }

    private function snapshotDiffers(Deal $deal, ExternalDealDTO $incoming): bool
    {
        foreach (FieldValue::NAMES as $field) {
            $stored = FieldValue::canonical($field, $deal->getAttribute('external_'.$field));
            if ($stored !== $incoming->field($field)) {
                return true;
            }
        }

        return false;
    }

    private function recordInconsistency(Deal $deal, ExternalDealDTO $incoming, SyncContext $context): void
    {
        $attributes = [
            'deal_id' => $deal->id,
            'external_id' => $incoming->id,
            'external_version' => $incoming->version,
            'source' => $context->source,
            'webhook_event_id' => $context->webhookEventId,
            'refresh_operation_id' => $context->refreshOperationId,
            'incoming' => [
                'title' => $incoming->title,
                'status' => $incoming->status,
                'amount' => $incoming->amount,
                'version' => $incoming->version,
                'updated_at' => $incoming->updatedAt->toIso8601String(),
            ],
            'stored' => [
                'title' => $deal->external_title,
                'status' => $deal->external_status,
                'amount' => $deal->external_amount,
                'version' => $deal->external_version,
                'updated_at' => $deal->external_updated_at?->toIso8601String(),
            ],
        ];

        if ($context->webhookEventId) {
            SourceInconsistency::query()->firstOrCreate(
                ['webhook_event_id' => $context->webhookEventId],
                $attributes,
            );

            return;
        }

        if ($context->refreshOperationId) {
            SourceInconsistency::query()->firstOrCreate(
                ['refresh_operation_id' => $context->refreshOperationId],
                $attributes,
            );
        }
    }

    private function log(Deal $deal, DealFieldOverride $override, string $state, int $version, string $remote): void
    {
        FieldConflictLog::query()->create([
            'deal_id' => $deal->id,
            'field_name' => $override->field_name,
            'state' => $state,
            'external_version' => $version,
            'base_value' => $override->base_value,
            'local_value' => $override->local_value,
            'remote_value' => $remote,
            'created_at' => now(),
        ]);
    }

    private function snapshotAttributes(ExternalDealDTO $incoming): array
    {
        return [
            'external_id' => $incoming->id,
            'title' => null,
            'status' => null,
            'amount' => null,
            'external_title' => $incoming->title,
            'external_status' => $incoming->status,
            'external_amount' => $incoming->amount,
            'external_version' => $incoming->version,
            'external_updated_at' => $incoming->updatedAt,
        ];
    }
}
