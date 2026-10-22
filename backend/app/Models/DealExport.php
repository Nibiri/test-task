<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealExport extends Model
{
    protected $fillable = [
        'deal_id',
        'idempotency_key',
        'payload',
        'status',
        'external_id',
        'attempt_count',
        'last_error',
        'scenario',
        'dispatch_after',
        'lease_until',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'dispatch_after' => 'datetime',
            'lease_until' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
