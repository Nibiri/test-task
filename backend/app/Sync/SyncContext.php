<?php

declare(strict_types=1);

namespace App\Sync;

final class SyncContext
{
    public function __construct(
        public readonly string $source,
        public readonly ?int $webhookEventId = null,
        public readonly ?int $refreshOperationId = null,
    ) {}
}
