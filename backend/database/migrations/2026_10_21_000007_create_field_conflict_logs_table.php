<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_conflict_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('field_name', 16);
            $table->string('state', 32);
            $table->unsignedInteger('external_version')->nullable();
            $table->text('base_value')->nullable();
            $table->text('local_value')->nullable();
            $table->text('remote_value')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['deal_id', 'id']);
        });

        DB::statement(
            "ALTER TABLE field_conflict_logs ADD CONSTRAINT field_conflict_logs_state_check CHECK (state IN ('detected', 'resolved_external', 'resolved_local', 'converged'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('field_conflict_logs');
    }
};
