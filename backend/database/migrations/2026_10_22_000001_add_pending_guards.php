<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Защита от двойного клика «Обновить из CRM» и «Повторить отправку».
        // В каждом состоянии accepted/running живёт не больше одной операции на сделку.
        DB::statement(
            "CREATE UNIQUE INDEX refresh_operations_one_pending
             ON refresh_operations (deal_id)
             WHERE status IN ('accepted', 'running')"
        );
        DB::statement(
            "CREATE UNIQUE INDEX deal_exports_one_pending
             ON deal_exports (deal_id)
             WHERE status IN ('accepted', 'running')"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS refresh_operations_one_pending');
        DB::statement('DROP INDEX IF EXISTS deal_exports_one_pending');
    }
};
