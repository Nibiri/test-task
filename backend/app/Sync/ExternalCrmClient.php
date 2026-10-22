<?php

declare(strict_types=1);

namespace App\Sync;

interface ExternalCrmClient
{
    public function fetch(string $externalId, ?string $scenario): FetchResult;

    /**
     * @param  array{title: string, status: string, amount: string}  $deal
     */
    public function create(string $idempotencyKey, array $deal, ?string $scenario): FetchResult;
}
