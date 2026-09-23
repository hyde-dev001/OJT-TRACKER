<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });

        Schema::table('internships', function (Blueprint $table) {
            $table->dropForeign(['coordinator_id']);
            $table->dropIndex(['coordinator_id', 'status']);
            $table->dropColumn('coordinator_id');
        });

        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['reviewer_remarks', 'submitted_at', 'reviewed_by', 'reviewed_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_by']);
            $table->dropIndex(['assigned_by', 'status']);
            $table->dropColumn(['assigned_by', 'submitted_at', 'reviewer_remarks']);
        });

        Schema::table('requirements', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['created_by', 'status']);
            $table->dropColumn([
                'created_by',
                'reviewer_remarks',
                'submitted_at',
                'reviewed_by',
                'reviewed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->index()->after('name');
        });

        Schema::table('internships', function (Blueprint $table) {
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['coordinator_id', 'status']);
        });

        Schema::table('work_logs', function (Blueprint $table) {
            $table->text('reviewer_remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->text('reviewer_remarks')->nullable();
            $table->index(['assigned_by', 'status']);
        });

        Schema::table('requirements', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reviewer_remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->index(['created_by', 'status']);
        });

        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
