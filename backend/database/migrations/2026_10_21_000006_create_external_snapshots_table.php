<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64);
            $table->unsignedInteger('version');
            $table->timestampTz('updated_at');
            $table->string('title');
            $table->string('status', 64);
            $table->decimal('amount', 14, 2);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_snapshots');
    }
};
