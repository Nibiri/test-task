<?php

declare(strict_types=1);

namespace App\Sync;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class HttpExternalCrmClient implements ExternalCrmClient
{
    public function fetch(string $externalId, ?string $scenario): FetchResult
    {
        $url = rtrim((string) config('sync.external_crm.base_url'), '/').'/mock-api/deals/'.$externalId;
        $query = $scenario ? ['scenario' => $scenario] : [];

        try {
            $response = Http::connectTimeout((float) config('sync.external_crm.connect_timeout'))
                ->timeout((float) config('sync.external_crm.timeout'))
                ->acceptJson()
                ->get($url, $query);
        } catch (ConnectionException) {
            return new FetchResult(0, [], null, 'connection failed');
        }

        $body = $response->json();
        $retry = $response->header('Retry-After');

        return new FetchResult(
            $response->status(),
            is_array($body) ? $body : [],
            is_numeric($retry) ? (int) $retry : null,
            null,
        );
    }

    public function create(string $idempotencyKey, array $deal, ?string $scenario): FetchResult
    {
        $url = rtrim((string) config('sync.external_crm.base_url'), '/').'/mock-api/deals';
        $query = $scenario ? ['scenario' => $scenario] : [];

        try {
            $response = Http::connectTimeout((float) config('sync.external_crm.connect_timeout'))
                ->timeout((float) config('sync.external_crm.timeout'))
                ->acceptJson()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post($url.'?'.http_build_query($query), [
                    'title' => $deal['title'],
                    'status' => $deal['status'],
                    'amount' => $deal['amount'],
                ]);
        } catch (ConnectionException) {
            return new FetchResult(0, [], null, 'connection failed');
        }

        $body = $response->json();
        $retry = $response->header('Retry-After');

        return new FetchResult(
            $response->status(),
            is_array($body) ? $body : [],
            is_numeric($retry) ? (int) $retry : null,
            null,
        );
    }
}
