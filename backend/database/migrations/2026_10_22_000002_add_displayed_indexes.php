<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Фильтр по "отображаемому" статусу без JOIN на override.
        DB::statement(
            'CREATE INDEX deals_displayed_status
             ON deals ((COALESCE(external_status, status)))'
        );

        // trigram на отображаемом названии — ILIKE по подстроке становится индексным.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement(
            'CREATE INDEX deals_title_trgm
             ON deals USING gin ((COALESCE(external_title, title)) gin_trgm_ops)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS deals_title_trgm');
        DB::statement('DROP INDEX IF EXISTS deals_displayed_status');
    }
};
