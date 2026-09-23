<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('work_logs')
            ->where('status', 'draft')
            ->update(['status' => 'completed']);
    }

    public function down(): void
    {
        // Existing records cannot be safely distinguished from logs created after this migration.
    }
};
