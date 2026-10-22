<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_snapshots', function (Blueprint $table) {
            $table->string('client_reference', 128)->nullable()->unique();
        });

        // The mock CRM issues ids. Seeded fixtures stay below this start.
        DB::statement('CREATE SEQUENCE IF NOT EXISTS mock_external_deal_ids START WITH 10000');

        Schema::create('deal_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->jsonb('payload');
            $table->string('status', 32);
            $table->string('external_id', 64)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->string('scenario', 32)->nullable();
            $table->timestampTz('dispatch_after')->nullable();
            $table->timestampTz('lease_until')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_exports');
        DB::statement('DROP SEQUENCE IF EXISTS mock_external_deal_ids');
        Schema::table('external_snapshots', function (Blueprint $table) {
            $table->dropUnique(['client_reference']);
            $table->dropColumn('client_reference');
        });
    }
};
