<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshOperation extends Model
{
    protected $fillable = [
        'deal_id',
        'status',
        'outcome',
        'outcome_detail',
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
            'outcome_detail' => 'array',
            'dispatch_after' => 'datetime',
            'lease_until' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
