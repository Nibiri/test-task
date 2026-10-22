<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_time',
        'external_id',
        'payload',
        'status',
        'result_reason',
        'result_detail',
        'error',
        'attempts',
        'dispatch_after',
        'lease_until',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
            'payload' => 'array',
            'result_detail' => 'array',
            'dispatch_after' => 'datetime',
            'lease_until' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'external_id', 'external_id');
    }
}
