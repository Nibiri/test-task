<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealFieldOverride;
use App\Models\WebhookEvent;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\Concerns\SendsWebhooks;
use Tests\TestCase;

class ConcurrentSyncTest extends TestCase
{
    use SendsWebhooks;

    protected array $connectionsToTransact = [];

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\DB::table('field_conflict_logs')->delete();
        \Illuminate\Support\Facades\DB::table('source_inconsistencies')->delete();
        \Illuminate\Support\Facades\DB::table('deal_field_overrides')->delete();
        \Illuminate\Support\Facades\DB::table('refresh_operations')->delete();
        \Illuminate\Support\Facades\DB::table('webhook_events')->delete();
        \Illuminate\Support\Facades\DB::table('deals')->delete();
        \Illuminate\Support\Facades\DB::table('external_snapshots')->delete();
        parent::tearDown();
    }

    #[Test]
    public function concurrent_duplicate_event_id_applies_once(): void
    {
        $body = [
            'event_id' => 'evt_concurrent',
            'event_time' => '2026-10-15T15:30:00Z',
            'deal' => $this->dealPayload(['title' => 'Once', 'amount' => 49000]),
        ];
        [$first, $second] = $this->both($this->webhookProcess($body), $this->webhookProcess($body));

        $statuses = [$this->statusOf($first), $this->statusOf($second)];
        sort($statuses);
        $this->assertSame([200, 202], $statuses);
        $this->assertSame(1, WebhookEvent::query()->where('event_id', 'evt_concurrent')->count());
        $this->assertSame(1, Deal::query()->where('external_id', '5678')->count());
        $this->assertSame('Once', Deal::query()->where('external_id', '5678')->first()->displayed('title'));
    }

    #[Test]
    public function concurrent_creation_of_the_same_external_id_keeps_one_deal(): void
    {
        $deal = $this->dealPayload(['version' => 5, 'title' => 'Single row']);
        $first = $this->webhookProcess([
            'event_id' => 'evt_create_a',
            'event_time' => '2026-10-15T15:30:00Z',
            'deal' => $deal,
        ]);
        $second = $this->webhookProcess([
            'event_id' => 'evt_create_b',
            'event_time' => '2026-10-15T15:30:01Z',
            'deal' => $deal,
        ]);
        [$left, $right] = $this->both($first, $second);

        $this->assertSame(202, $this->statusOf($left));
        $this->assertSame(202, $this->statusOf($right));
        $this->assertSame(1, Deal::query()->where('external_id', '5678')->count());
        $this->assertSame(5, Deal::query()->where('external_id', '5678')->first()->external_version);
    }

    #[Test]
    public function concurrent_local_edit_and_external_sync_keep_both_updates(): void
    {
        $this->postWebhook($this->dealPayload(), 'evt_start')->assertStatus(202);
        $deal = Deal::query()->where('external_id', '5678')->firstOrFail();

        $webhook = $this->webhookProcess([
            'event_id' => 'evt_title_race',
            'event_time' => '2026-10-16T12:00:00Z',
            'deal' => $this->dealPayload([
                'version' => 19,
                'updated_at' => '2026-10-16T12:00:00Z',
                'title' => 'Advanced Python Course',
                'amount' => 49000,
            ]),
        ]);
        $edit = new Process([PHP_BINARY, base_path('tests/bin/patch_deal.php'), (string) $deal->id, json_encode(['amount' => 45000])]);
        $edit->setTimeout(30);
        $edit->setEnv($this->childEnvironment());
        [$webhookResult, $editResult] = $this->both($webhook, $edit);

        $this->assertSame(202, $this->statusOf($webhookResult), $webhookResult->getErrorOutput().$webhookResult->getOutput());
        $this->assertSame(200, $this->statusOf($editResult), $editResult->getErrorOutput().$editResult->getOutput());

        $deal->refresh()->load('overrides');
        $override = $deal->overrides->firstWhere('field_name', 'amount');
        $this->assertSame(19, $deal->external_version);
        $this->assertSame('Advanced Python Course', $deal->displayed('title'));
        $this->assertInstanceOf(DealFieldOverride::class, $override);
        $this->assertSame('45000.00', $override->local_value);
        $this->assertSame('49000.00', $deal->external_amount);
    }

    private function webhookProcess(array $body): Process
    {
        $path = tempnam(sys_get_temp_dir(), 'wh');
        file_put_contents($path, json_encode($body));
        $process = new Process([PHP_BINARY, base_path('tests/bin/post_webhook.php'), $path]);
        $process->setTimeout(30);
        $process->setEnv($this->childEnvironment());

        return $process;
    }

    /**
     * @return array{0: Process, 1: Process}
     */
    private function both(Process $left, Process $right): array
    {
        $left->start();
        $right->start();
        $left->wait();
        $right->wait();

        return [$left, $right];
    }

    /**
     * @return array<string, string>
     */
    private function childEnvironment(): array
    {
        $env = getenv();
        unset($env['DB_URL']);

        return $env;
    }

    private function statusOf(Process $process): int
    {
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());

        return (int) strtok($process->getOutput(), "\n");
    }
}
