<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks', 'requirements'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('submission_status')->default('not_submitted');
                $table->string('proof_path')->nullable();
                $table->text('submission_comment')->nullable();
                $table->text('review_comment')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'requirements'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn([
                    'submission_status',
                    'proof_path',
                    'submission_comment',
                    'review_comment',
                    'submitted_at',
                    'reviewed_at',
                ]);
            });
        }
    }
};
