<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DealFieldOverride;
use App\Models\FieldConflictLog;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SendsWebhooks;
use Tests\TestCase;

class FieldMergeTest extends TestCase
{
    use SendsWebhooks;

    #[Test]
    public function local_modification_with_unchanged_external_field_preserves_local_value(): void
    {
        $deal = $this->syncedDeal();
        $this->patchJson('/api/deals/'.$deal->id, ['amount' => 45000])->assertOk();

        $this->postWebhook($this->dealPayload([
            'version' => 19,
            'updated_at' => '2026-10-16T10:00:00Z',
            'title' => 'Python Course',
            'amount' => 49000,
        ]), 'evt_same_amount')->assertStatus(202);

        $deal->refresh()->load('overrides');
        $override = $deal->overrides->firstWhere('field_name', 'amount');

        $this->assertSame('45000.00', $deal->displayed('amount'));
        $this->assertSame('49000.00', $deal->external_amount);
        $this->assertSame(19, $deal->external_version);
        $this->assertNotNull($override);
        $this->assertFalse($override->is_conflicted);
        $this->assertSame('49000.00', $override->base_value);
    }

    #[Test]
    public function external_modification_without_local_override_applies_automatically(): void
    {
        $deal = $this->syncedDeal();

        $this->postWebhook($this->dealPayload([
            'version' => 19,
            'updated_at' => '2026-10-16T10:00:00Z',
            'title' => 'Advanced Python Course',
        ]), 'evt_title')->assertStatus(202);

        $deal->refresh()->load('overrides');
        $this->assertSame('Advanced Python Course', $deal->displayed('title'));
        $this->assertSame('Advanced Python Course', $deal->external_title);
        $this->assertTrue($deal->overrides->isEmpty());
    }

    #[Test]
    public function independent_field_changes_merge_and_the_same_field_conflicts(): void
    {
        $deal = $this->syncedDeal();
        $this->patchJson('/api/deals/'.$deal->id, ['amount' => 45000])->assertOk();

        $this->postWebhook($this->dealPayload([
            'version' => 19,
            'updated_at' => '2026-10-16T12:00:00Z',
            'title' => 'Advanced Python Course',
            'status' => 'NEW',
            'amount' => 52000,
        ]), 'evt_v19')->assertStatus(202);

        $deal->refresh()->load('overrides');
        $amount = $deal->overrides->firstWhere('field_name', 'amount');

        $this->assertSame('Advanced Python Course', $deal->displayed('title'));
        $this->assertSame('NEW', $deal->displayed('status'));
        $this->assertSame('45000.00', $deal->displayed('amount'));
        $this->assertSame('52000.00', $deal->external_amount);
        $this->assertSame(19, $deal->external_version);
        $this->assertTrue($amount->is_conflicted);
        $this->assertNull($deal->overrides->firstWhere('field_name', 'title'));
        $this->assertDatabaseHas('field_conflict_logs', [
            'deal_id' => $deal->id,
            'field_name' => 'amount',
            'state' => FieldConflictLog::DETECTED,
        ]);
    }

    #[Test]
    public function convergent_values_remove_the_local_override(): void
    {
        $deal = $this->syncedDeal();
        $this->patchJson('/api/deals/'.$deal->id, ['amount' => 45000])->assertOk();

        $this->postWebhook($this->dealPayload([
            'version' => 19,
            'updated_at' => '2026-10-16T12:00:00Z',
            'amount' => 45000,
        ]), 'evt_converged')->assertStatus(202);

        $deal->refresh()->load('overrides');
        $this->assertTrue($deal->overrides->isEmpty());
        $this->assertSame('45000.00', $deal->displayed('amount'));
        $this->assertSame('45000.00', $deal->external_amount);
        $this->assertDatabaseHas('field_conflict_logs', [
            'deal_id' => $deal->id,
            'field_name' => 'amount',
            'state' => FieldConflictLog::CONVERGED,
        ]);
    }

    #[Test]
    public function accepting_external_removes_the_override(): void
    {
        $deal = $this->conflictedAmount();

        $this->postJson('/api/deals/'.$deal->id.'/resolutions', [
            'field' => 'amount',
            'action' => 'accept_external',
            'external_version' => 19,
        ])->assertOk();

        $deal->refresh()->load('overrides');
        $this->assertTrue($deal->overrides->isEmpty());
        $this->assertSame('52000.00', $deal->displayed('amount'));
        $this->assertSame('52000.00', $deal->external_amount);
        $this->assertDatabaseHas('field_conflict_logs', [
            'deal_id' => $deal->id,
            'state' => FieldConflictLog::RESOLVED_EXTERNAL,
        ]);
    }

    #[Test]
    public function keeping_local_acknowledges_the_external_value_without_changing_it(): void
    {
        $deal = $this->conflictedAmount();

        $this->postJson('/api/deals/'.$deal->id.'/resolutions', [
            'field' => 'amount',
            'action' => 'keep_local',
            'external_version' => 19,
        ])->assertOk();

        $deal->refresh()->load('overrides');
        $override = $deal->overrides->firstWhere('field_name', 'amount');
        $this->assertSame('45000.00', $deal->displayed('amount'));
        $this->assertSame('52000.00', $deal->external_amount);
        $this->assertSame('52000.00', $override->base_value);
        $this->assertSame('45000.00', $override->local_value);
        $this->assertFalse($override->is_conflicted);
        $this->assertDatabaseHas('field_conflict_logs', [
            'deal_id' => $deal->id,
            'state' => FieldConflictLog::RESOLVED_LOCAL,
        ]);
    }

    #[Test]
    public function stale_conflict_resolution_returns_409(): void
    {
        $deal = $this->conflictedAmount();
        $this->postWebhook($this->dealPayload([
            'version' => 20,
            'updated_at' => '2026-10-17T12:00:00Z',
            'title' => 'Advanced Python Course',
            'amount' => 53000,
        ]), 'evt_v20')->assertStatus(202);

        $this->postJson('/api/deals/'.$deal->id.'/resolutions', [
            'field' => 'amount',
            'action' => 'accept_external',
            'external_version' => 19,
        ])->assertStatus(409);

        $deal->refresh()->load('overrides');
        $this->assertSame(20, $deal->external_version);
        $this->assertNotNull($deal->overrides->firstWhere('field_name', 'amount'));
        $this->assertSame('45000.00', $deal->displayed('amount'));
    }

    #[Test]
    public function repeated_local_edits_keep_the_original_base(): void
    {
        $deal = $this->syncedDeal();
        $this->patchJson('/api/deals/'.$deal->id, ['amount' => 45000])->assertOk();
        $this->patchJson('/api/deals/'.$deal->id, ['amount' => 44000])->assertOk();

        $override = DealFieldOverride::query()->where('deal_id', $deal->id)->where('field_name', 'amount')->first();
        $this->assertSame('49000.00', $override->base_value);
        $this->assertSame('44000.00', $override->local_value);
    }

    private function syncedDeal(): Deal
    {
        $this->postWebhook($this->dealPayload(), 'evt_base')->assertStatus(202);

        return Deal::query()->where('external_id', '5678')->firstOrFail();
    }

    private function conflictedAmount(): Deal
    {
        $deal = $this->syncedDeal();
        $this->patchJson('/api/deals/'.$deal->id, ['amount' => 45000])->assertOk();
        $this->postWebhook($this->dealPayload([
            'version' => 19,
            'updated_at' => '2026-10-16T12:00:00Z',
            'title' => 'Advanced Python Course',
            'amount' => 52000,
        ]), 'evt_conflict')->assertStatus(202);

        return $deal->fresh();
    }
}
