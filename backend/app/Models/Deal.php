<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\FieldValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deal extends Model
{
    protected $fillable = [
        'external_id',
        'title',
        'status',
        'amount',
        'external_title',
        'external_status',
        'external_amount',
        'external_version',
        'external_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'external_amount' => 'decimal:2',
            'external_version' => 'integer',
            'external_updated_at' => 'datetime',
        ];
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(DealFieldOverride::class);
    }

    public function conflictLogs(): HasMany
    {
        return $this->hasMany(FieldConflictLog::class);
    }

    public function refreshOperations(): HasMany
    {
        return $this->hasMany(RefreshOperation::class);
    }

    public function latestRefresh(): HasOne
    {
        return $this->hasOne(RefreshOperation::class)->latestOfMany();
    }

    public function export(): HasOne
    {
        return $this->hasOne(DealExport::class);
    }

    public function displayed(string $field): ?string
    {
        $this->loadMissing('overrides');
        $override = $this->overrides->firstWhere('field_name', $field);
        if ($override) {
            return $override->local_value;
        }

        $external = $this->getAttribute('external_'.$field);
        if ($this->external_version !== null && $external !== null) {
            return FieldValue::canonical($field, $external);
        }

        $manual = $this->getAttribute($field);

        return $manual === null ? null : FieldValue::canonical($field, $manual);
    }

    public function toApiArray(): array
    {
        $this->loadMissing(['overrides', 'latestRefresh', 'export']);

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'title' => $this->displayed('title'),
            'status' => $this->displayed('status'),
            'amount' => $this->displayed('amount'),
            'external_title' => $this->external_title,
            'external_status' => $this->external_status,
            'external_amount' => $this->external_amount,
            'external_version' => $this->external_version,
            'external_updated_at' => $this->external_updated_at?->toIso8601String(),
            'has_conflict' => $this->overrides->contains(fn (DealFieldOverride $override) => $override->is_conflicted),
            'overrides' => $this->overrides->map(fn (DealFieldOverride $override) => [
                'field' => $override->field_name,
                'base' => $override->base_value,
                'local' => $override->local_value,
                'external' => $this->external_version === null ? null : FieldValue::canonical($override->field_name, $this->getAttribute('external_'.$override->field_name)),
                'is_conflicted' => $override->is_conflicted,
            ])->values(),
            'latest_refresh' => $this->latestRefresh ? [
                'id' => $this->latestRefresh->id,
                'status' => $this->latestRefresh->status,
                'outcome' => $this->latestRefresh->outcome,
                'outcome_detail' => $this->latestRefresh->outcome_detail,
                'attempt_count' => $this->latestRefresh->attempt_count,
                'last_error' => $this->latestRefresh->last_error,
                'finished_at' => $this->latestRefresh->finished_at?->toIso8601String(),
            ] : null,
            'latest_export' => $this->export ? [
                'id' => $this->export->id,
                'status' => $this->export->status,
                'attempt_count' => $this->export->attempt_count,
                'last_error' => $this->export->last_error,
                'finished_at' => $this->export->finished_at?->toIso8601String(),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
