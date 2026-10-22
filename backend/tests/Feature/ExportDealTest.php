<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealExport;
use App\Models\ExternalSnapshot;
use App\Sync\MockExternalDealGateway;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExportDealTest extends TestCase
{
    #[Test]
    public function creating_a_deal_stores_the_id_issued_by_the_external_crm(): void
    {
        $response = $this->postJson('/api/deals', [
            'title' => 'Новый курс',
            'status' => 'NEW',
            'amount' => 1500,
        ])->assertStatus(202);

        $deal = Deal::query()->findOrFail($response->json('data.id'));
        $this->assertNotNull($deal->external_id);
        $this->assertSame(1, $deal->external_version);
        $this->assertSame('Новый курс', $deal->displayed('title'));
        $this->assertSame('1500.00', $deal->displayed('amount'));
        $this->assertNull($deal->title);
        $this->assertSame('succeeded', $deal->export->status);
        $this->assertSame($deal->external_id, ExternalSnapshot::query()->where('client_reference', 'deal-'.$deal->id)->value('external_id'));
    }

    #[Test]
    public function repeating_the_same_idempotency_key_returns_the_same_external_id(): void
    {
        $this->postJson('/api/deals', [
            'title' => 'Новый курс',
            'status' => 'NEW',
            'amount' => 1500,
        ])->assertStatus(202);

        $deal = Deal::query()->firstOrFail();
        $gateway = $this->app->make(MockExternalDealGateway::class);
        $again = $gateway->create('deal-'.$deal->id, 'Другое имя', 'WON', '1.00', 'success');

        $this->assertSame(200, $again['status']);
        $this->assertSame($deal->external_id, $again['body']['id']);
        $this->assertSame('Новый курс', $again['body']['title']);
        $this->assertSame(1, ExternalSnapshot::query()->where('client_reference', 'deal-'.$deal->id)->count());
    }

    #[Test]
    public function a_failing_crm_keeps_the_deal_without_an_external_id(): void
    {
        $response = $this->postJson('/api/deals', [
            'title' => 'Не ушло',
            'status' => 'NEW',
            'amount' => 10,
            'scenario' => 'failure',
        ])->assertStatus(202);

        $deal = Deal::query()->findOrFail($response->json('data.id'));
        $this->assertNull($deal->external_id);
        $this->assertSame('Не ушло', $deal->displayed('title'));
        $this->assertSame('failed', DealExport::query()->where('deal_id', $deal->id)->value('status'));
        $this->assertSame(3, DealExport::query()->where('deal_id', $deal->id)->value('attempt_count'));
    }
}
