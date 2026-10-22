<?php

namespace Database\Seeders;

use App\Models\Deal;
use App\Models\ExternalSnapshot;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $snapshots = [
            ['external_id' => '5678', 'version' => 18, 'updated_at' => '2026-10-15 15:29:50+00', 'title' => 'Продажа курса', 'status' => 'NEW', 'amount' => '49000.00'],
            ['external_id' => '1001', 'version' => 4, 'updated_at' => '2026-10-16 10:00:00+00', 'title' => 'Абонемент', 'status' => 'IN_PROGRESS', 'amount' => '12000.00'],
            ['external_id' => '1002', 'version' => 2, 'updated_at' => '2026-10-17 10:00:00+00', 'title' => 'Интенсив', 'status' => 'WON', 'amount' => '80000.00'],
        ];

        foreach ($snapshots as $row) {
            ExternalSnapshot::query()->firstOrCreate(['external_id' => $row['external_id']], $row);
        }

        if (Deal::query()->exists()) {
            return;
        }

        foreach ($snapshots as $row) {
            Deal::query()->create([
                'external_id' => $row['external_id'],
                'external_title' => $row['title'],
                'external_status' => $row['status'],
                'external_amount' => $row['amount'],
                'external_version' => $row['version'],
                'external_updated_at' => $row['updated_at'],
            ]);
        }

        foreach (range(1, 9) as $i) {
            Deal::query()->create([
                'title' => 'Ручная сделка '.$i,
                'status' => ['NEW', 'IN_PROGRESS', 'WON', 'LOST'][$i % 4],
                'amount' => (1000 * $i).'.00',
            ]);
        }
    }
}
