<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExternalSnapshot;
use App\Support\FieldValue;
use Illuminate\Console\Command;

class SetMockSnapshot extends Command
{
    protected $signature = 'mock:set {externalId} {--external-version=} {--title=} {--status=} {--amount=} {--updated_at=}';

    protected $description = 'Create or update a mock external deal snapshot';

    public function handle(): int
    {
        $externalId = (string) $this->argument('externalId');
        $snapshot = ExternalSnapshot::query()->firstOrNew(['external_id' => $externalId]);
        if (! $snapshot->exists && $this->option('external-version') === null) {
            $this->error('Для новой сделки укажите --external-version.');

            return self::FAILURE;
        }

        if ($this->option('external-version') !== null) {
            $snapshot->version = (int) $this->option('external-version');
        }
        if ($this->option('title') !== null) {
            $snapshot->title = (string) $this->option('title');
        }
        if ($this->option('status') !== null) {
            $snapshot->status = (string) $this->option('status');
        }
        if ($this->option('amount') !== null) {
            $snapshot->amount = FieldValue::canonicalAmount($this->option('amount'));
        }
        $snapshot->updated_at = $this->option('updated_at') ?: ($snapshot->updated_at ?: now());
        if (! $snapshot->title) {
            $snapshot->title = 'Сделка '.$externalId;
        }
        if (! $snapshot->status) {
            $snapshot->status = 'NEW';
        }
        if ($snapshot->amount === null) {
            $snapshot->amount = '0.00';
        }
        $snapshot->save();
        $this->info('Снимок '.$externalId.' сохранён, версия '.$snapshot->version.'.');

        return self::SUCCESS;
    }
}
