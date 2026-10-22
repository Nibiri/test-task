<?php

declare(strict_types=1);

namespace App\Sync;

final class FetchResult
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly ?int $retryAfterSeconds,
        public readonly ?string $transportError,
    ) {}
}
