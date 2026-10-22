<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_field_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('field_name', 16);
            $table->text('base_value');
            $table->text('local_value');
            $table->boolean('is_conflicted')->default(false);
            $table->timestamps();

            // One override row per field. Concurrent edits cannot insert two bases.
            $table->unique(['deal_id', 'field_name']);
        });

        DB::statement(
            "ALTER TABLE deal_field_overrides ADD CONSTRAINT deal_field_overrides_field_name_check CHECK (field_name IN ('title', 'status', 'amount'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_field_overrides');
    }
};
