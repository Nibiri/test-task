<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\ExternalSnapshot;
use App\Models\SourceInconsistency;
use App\Models\WebhookEvent;
use App\Sync\DispatchesSyncJobs;
use App\Sync\QueueSyncDispatcher;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\SendsWebhooks;
use Tests\TestCase;

class VersionSafetyTest extends TestCase
{
    use SendsWebhooks;

    #[Test]
    public function duplicate_event_id_applies_the_change_once(): void
    {
        $payload = $this->dealPayload(['amount' => 49000]);
        $this->postWebhook($payload, 'evt_once')->assertStatus(202);
        $this->postWebhook($payload, 'evt_once')->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, WebhookEvent::query()->where('event_id', 'evt_once')->count());
        $this->assertSame(1, Deal::query()->where('external_id', '5678')->count());
        $this->assertSame('49000.00', Deal::query()->where('external_id', '5678')->first()->displayed('amount'));
    }

    #[Test]
    public function older_webhook_and_get_do_not_overwrite_a_newer_version(): void
    {
        $this->postWebhook($this->dealPayload([
            'version' => 18,
            'title' => 'Newer title',
            'amount' => 49000,
        ]), 'evt_18')->assertStatus(202);

        $this->postWebhook($this->dealPayload([
            'version' => 17,
            'updated_at' => '2026-10-14T10:00:00Z',
            'title' => 'Older title',
            'amount' => 1000,
        ]), 'evt_17')->assertStatus(202);

        $deal = Deal::query()->where('external_id', '5678')->firstOrFail();
        $this->assertSame(18, $deal->external_version);
        $this->assertSame('Newer title', $deal->displayed('title'));
        $this->assertSame('applied', WebhookEvent::query()->where('event_id', 'evt_18')->value('status'));
        $this->assertSame('stale_version', WebhookEvent::query()->where('event_id', 'evt_17')->value('result_reason'));

        ExternalSnapshot::query()->create([
            'external_id' => '5678',
            'version' => 17,
            'updated_at' => '2026-10-14 10:00:00+00',
            'title' => 'Older from GET',
            'status' => 'LOST',
            'amount' => '1000.00',
        ]);

        $this->postJson('/api/deals/'.$deal->id.'/refresh', ['scenario' => 'success'])->assertStatus(202);
        $deal->refresh();
        $this->assertSame(18, $deal->external_version);
        $this->assertSame('Newer title', $deal->displayed('title'));
        $this->assertSame('skipped', $deal->latestRefresh->outcome);
        $this->assertSame('stale_version', $deal->latestRefresh->outcome_detail['reason']);
    }

    #[Test]
    public function equal_version_with_a_different_payload_is_an_inconsistency(): void
    {
        $this->postWebhook($this->dealPayload(['title' => 'Python Course']), 'evt_a')->assertStatus(202);
        $this->postWebhook($this->dealPayload([
            'title' => 'Different at the same version',
            'updated_at' => '2026-10-15T18:00:00Z',
        ]), 'evt_b')->assertStatus(202);

        $deal = Deal::query()->where('external_id', '5678')->firstOrFail();
        $this->assertSame('Python Course', $deal->external_title);
        $this->assertSame('Python Course', $deal->displayed('title'));
        $this->assertSame(18, $deal->external_version);
        $this->assertSame(1, SourceInconsistency::query()->count());
        $this->assertSame('source_inconsistency', WebhookEvent::query()->where('event_id', 'evt_b')->value('result_reason'));
    }

    #[Test]
    public function a_persisted_webhook_is_recovered_after_dispatch_failure(): void
    {
        $this->app->instance(DispatchesSyncJobs::class, new class implements DispatchesSyncJobs
        {
            public function dispatchWebhook(int $eventId): void
            {
                throw new RuntimeException('redis down');
            }

            public function dispatchRefresh(int $operationId): void
            {
                throw new RuntimeException('redis down');
            }

            public function dispatchExport(int $exportId): void
            {
                throw new RuntimeException('redis down');
            }
        });

        $this->postWebhook($this->dealPayload(), 'evt_recover')->assertStatus(202);
        $this->assertNull(Deal::query()->where('external_id', '5678')->first());
        $this->assertSame('received', WebhookEvent::query()->where('event_id', 'evt_recover')->value('status'));

        $this->app->instance(DispatchesSyncJobs::class, $this->app->make(QueueSyncDispatcher::class));
        $this->artisan('sync:recover')->assertSuccessful();

        $deal = Deal::query()->where('external_id', '5678')->first();
        $this->assertNotNull($deal);
        $this->assertSame('Python Course', $deal->displayed('title'));
        $this->assertSame('applied', WebhookEvent::query()->where('event_id', 'evt_recover')->value('status'));
    }

    #[Test]
    public function a_bad_token_is_rejected_without_storing_the_event(): void
    {
        $this->postJson('/api/webhooks/deals', [
            'event_id' => 'evt_bad',
            'event_time' => '2026-10-15T15:30:00Z',
            'deal' => $this->dealPayload(),
        ], ['X-Webhook-Token' => 'wrong'])->assertUnauthorized();

        $this->assertSame(0, WebhookEvent::query()->count());
    }
}
