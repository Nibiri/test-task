<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_inconsistencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id', 64);
            $table->unsignedInteger('external_version');
            $table->string('source', 32);
            $table->foreignId('webhook_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refresh_operation_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('incoming');
            $table->jsonb('stored');
            $table->timestamps();

            // A replayed event records the mismatch once.
            $table->unique('webhook_event_id');
            $table->unique('refresh_operation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_inconsistencies');
    }
};
