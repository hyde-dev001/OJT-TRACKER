<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $demoStudentId = DB::table('users')
            ->where('email', 'student@example.com')
            ->value('id');

        if ($demoStudentId) {
            DB::table('internships')
                ->where('student_id', $demoStudentId)
                ->whereNull('end_date')
                ->update([
                    'end_date' => '2026-12-15',
                    'work_days' => json_encode([1, 2, 3, 4, 5], JSON_THROW_ON_ERROR),
                    'expected_daily_minutes' => 480,
                ]);
        }

        $invalidInternships = DB::table('internships')
            ->where(function ($query): void {
                $query->whereNull('end_date')->orWhereColumn('end_date', '<=', 'start_date');
            })
            ->pluck('id');

        if ($invalidInternships->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot require internship end dates. Resolve internship IDs: '.$invalidInternships->implode(', ').'.'
            );
        }

        DB::table('work_logs')
            ->where('status', 'draft')
            ->update(['status' => 'completed']);

        $unexpectedWorkLogStatuses = DB::table('work_logs')
            ->whereNotIn('status', ['completed'])
            ->distinct()
            ->pluck('status');

        if ($unexpectedWorkLogStatuses->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot normalize work-log statuses: '.$unexpectedWorkLogStatuses->implode(', ').'.'
            );
        }

        DB::table('requirements')
            ->whereIn('status', ['approved'])
            ->update(['status' => 'completed']);
        DB::table('requirements')
            ->whereIn('status', ['not_submitted', 'not_completed', 'submitted', 'rejected', 'needs_revision'])
            ->update(['status' => 'incomplete']);

        $unexpectedRequirementStatuses = DB::table('requirements')
            ->whereNotIn('status', ['incomplete', 'completed'])
            ->distinct()
            ->pluck('status');

        if ($unexpectedRequirementStatuses->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot normalize requirement statuses: '.$unexpectedRequirementStatuses->implode(', ').'.'
            );
        }

        Schema::table('internships', function (Blueprint $table): void {
            $table->date('end_date')->nullable(false)->change();
        });
        Schema::table('work_logs', function (Blueprint $table): void {
            $table->string('status')->default('completed')->change();
        });
        Schema::table('requirements', function (Blueprint $table): void {
            $table->string('status')->default('incomplete')->change();
        });
    }

    public function down(): void
    {
        Schema::table('internships', function (Blueprint $table): void {
            $table->date('end_date')->nullable()->change();
        });
        Schema::table('work_logs', function (Blueprint $table): void {
            $table->string('status')->default('draft')->change();
        });
        Schema::table('requirements', function (Blueprint $table): void {
            $table->string('status')->default('not_submitted')->change();
        });
    }
};
