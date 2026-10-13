<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table): void {
            $table->time('time_out')->nullable()->change();
            $table->unsignedInteger('rendered_minutes')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('work_logs')->whereNull('time_out')->orWhereNull('rendered_minutes')->exists()) {
            throw new \RuntimeException('Cannot make work log fields required while open sessions exist.');
        }

        Schema::table('work_logs', function (Blueprint $table): void {
            $table->time('time_out')->nullable(false)->change();
            $table->unsignedInteger('rendered_minutes')->nullable(false)->change();
        });
    }
};
