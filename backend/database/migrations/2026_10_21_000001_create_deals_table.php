<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64)->nullable();
            // Manual deals keep their fields here until an external snapshot exists.
            // After that, the API displays an override or the external_* columns.
            $table->string('title')->nullable();
            $table->string('status', 64)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('external_title')->nullable();
            $table->string('external_status', 64)->nullable();
            $table->decimal('external_amount', 14, 2)->nullable();
            $table->unsignedInteger('external_version')->nullable();
            $table->timestampTz('external_updated_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('external_status');
        });

        // One local row per external deal. Multiple manual deals keep a null external_id.
        // The unique index is also the lock that serializes concurrent first inserts.
        DB::statement(
            'CREATE UNIQUE INDEX deals_external_id_unique ON deals (external_id) WHERE external_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
