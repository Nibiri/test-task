<?php

declare(strict_types=1);

namespace App\Sync;

final class InProcessExternalCrmClient implements ExternalCrmClient
{
    public function __construct(private readonly MockExternalDealGateway $gateway) {}

    public function fetch(string $externalId, ?string $scenario): FetchResult
    {
        $result = $this->gateway->respond($externalId, $scenario);

        return new FetchResult($result['status'], $result['body'], $result['retry_after'], null);
    }

    public function create(string $idempotencyKey, array $deal, ?string $scenario): FetchResult
    {
        $result = $this->gateway->create(
            $idempotencyKey,
            $deal['title'],
            $deal['status'],
            $deal['amount'],
            $scenario,
        );

        return new FetchResult($result['status'], $result['body'], $result['retry_after'], null);
    }
}
