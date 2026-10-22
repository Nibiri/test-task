<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait SendsWebhooks
{
    protected function dealPayload(array $overrides = []): array
    {
        return array_replace([
            'id' => '5678',
            'version' => 18,
            'updated_at' => '2026-10-15T15:29:50Z',
            'title' => 'Python Course',
            'status' => 'NEW',
            'amount' => 49000,
        ], $overrides);
    }

    protected function postWebhook(array $deal, string $eventId, ?string $eventTime = null): TestResponse
    {
        return $this->postJson('/api/webhooks/deals', [
            'event_id' => $eventId,
            'event_time' => $eventTime ?? '2026-10-15T15:30:00Z',
            'deal' => $deal,
        ], ['X-Webhook-Token' => 'test-webhook-token']);
    }
}
