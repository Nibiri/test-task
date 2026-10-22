<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldConflictLog extends Model
{
    public const DETECTED = 'detected';

    public const RESOLVED_EXTERNAL = 'resolved_external';

    public const RESOLVED_LOCAL = 'resolved_local';

    public const CONVERGED = 'converged';

    public $timestamps = false;

    protected $fillable = [
        'deal_id',
        'field_name',
        'state',
        'external_version',
        'base_value',
        'local_value',
        'remote_value',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'external_version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
