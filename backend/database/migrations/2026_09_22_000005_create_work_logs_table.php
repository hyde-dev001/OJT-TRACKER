<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('time_in');
            $table->time('time_out');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('rendered_minutes');
            $table->text('accomplishment_summary')->nullable();
            $table->string('status')->default('draft');
            $table->text('reviewer_remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['internship_id', 'work_date']);
            $table->index(['internship_id', 'status']);
            $table->index(['status', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
