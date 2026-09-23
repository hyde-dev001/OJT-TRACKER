<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('required_minutes');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['coordinator_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internships');
    }
};
