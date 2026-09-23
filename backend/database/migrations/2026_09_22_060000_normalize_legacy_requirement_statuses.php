<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('requirements')
            ->whereIn('status', ['approved'])
            ->update(['status' => 'completed']);

        DB::table('requirements')
            ->whereIn('status', ['not_submitted', 'submitted', 'rejected', 'needs_revision'])
            ->update(['status' => 'not_completed']);
    }

    public function down(): void
    {
        // Legacy review states cannot be reconstructed safely after normalization.
    }
};
