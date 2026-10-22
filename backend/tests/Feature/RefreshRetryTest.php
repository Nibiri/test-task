<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\ExternalSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SendsWebhooks;
use Tests\TestCase;

class RefreshRetryTest extends TestCase
{
    use SendsWebhooks;

    #[Test]
    public function rate_limit_then_success_updates_the_deal(): void
    {
        $deal = $this->dealAndSnapshot(19, 'After retry');
        config(['queue.default' => 'redis']);

        $this->postJson('/api/deals/'.$deal->id.'/refresh', ['scenario' => 'rate_limit'])->assertStatus(202);
        $this->assertSame(1, Queue::connection('redis')->size());
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--tries' => 1])->assertSuccessful();

        $deal->refresh();
        $this->assertSame(0, Queue::connection('redis')->size());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame('succeeded', $deal->latestRefresh->status);
        $this->assertSame('applied', $deal->latestRefresh->outcome);
        $this->assertSame(3, $deal->latestRefresh->attempt_count);
        $this->assertSame(19, $deal->external_version);
        $this->assertSame('After retry', $deal->displayed('title'));
    }

    #[Test]
    public function persistent_failure_is_terminal(): void
    {
        $deal = $this->dealAndSnapshot(19, 'Never applied');
        config(['queue.default' => 'redis']);

        $this->postJson('/api/deals/'.$deal->id.'/refresh', ['scenario' => 'failure'])->assertStatus(202);
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--tries' => 1])->assertSuccessful();

        $deal->refresh();
        $this->assertSame(0, Queue::connection('redis')->size());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame('failed', $deal->latestRefresh->status);
        $this->assertSame(3, $deal->latestRefresh->attempt_count);
        $this->assertStringContainsString('500', (string) $deal->latestRefresh->last_error);
        $this->assertSame(18, $deal->external_version);
        $this->assertSame('Python Course', $deal->displayed('title'));
    }

    #[Test]
    public function an_ordinary_client_error_is_not_retried(): void
    {
        $this->postWebhook($this->dealPayload(), 'evt_missing_snapshot')->assertStatus(202);
        $deal = Deal::query()->where('external_id', '5678')->firstOrFail();

        $this->postJson('/api/deals/'.$deal->id.'/refresh', ['scenario' => 'success'])->assertStatus(202);

        $deal->refresh();
        $this->assertSame('failed', $deal->latestRefresh->status);
        $this->assertSame(1, $deal->latestRefresh->attempt_count);
        $this->assertStringContainsString('404', (string) $deal->latestRefresh->last_error);
    }

    private function dealAndSnapshot(int $version, string $title): Deal
    {
        $this->postWebhook($this->dealPayload(), 'evt_seed_'.$title)->assertStatus(202);
        ExternalSnapshot::query()->create([
            'external_id' => '5678',
            'version' => $version,
            'updated_at' => '2026-10-16 12:00:00+00',
            'title' => $title,
            'status' => 'NEW',
            'amount' => '49000.00',
        ]);

        return Deal::query()->where('external_id', '5678')->firstOrFail();
    }
}
