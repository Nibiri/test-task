<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id');
            $table->timestampTz('event_time');
            $table->string('external_id', 64);
            $table->jsonb('payload');
            $table->string('status', 32);
            $table->string('result_reason')->nullable();
            $table->jsonb('result_detail')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('dispatch_after')->nullable();
            $table->timestampTz('lease_until')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();

            // Duplicate and concurrent deliveries collapse on this key.
            $table->unique('event_id');
            // Recovery scans only rows that are due, not the whole inbox.
            $table->index(['status', 'dispatch_after']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
