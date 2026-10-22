<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('outcome')->nullable();
            $table->jsonb('outcome_detail')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->string('scenario', 32)->nullable();
            $table->timestampTz('dispatch_after')->nullable();
            $table->timestampTz('lease_until')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            // Recovery and "what is still running" look at status over time.
            $table->index(['status', 'created_at']);
            // The deal card reads the latest operation.
            $table->index(['deal_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_operations');
    }
};
