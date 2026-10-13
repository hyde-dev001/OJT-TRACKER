<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('student')->index()->after('name');
            $table->boolean('must_change_password')->default(false)->after('role');
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('account_holder_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('internships', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('student_id')->constrained('companies')->restrictOnDelete();
            $table->unsignedInteger('required_minutes')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('internships', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
            $table->unsignedInteger('required_minutes')->nullable(false)->change();
        });

        Schema::dropIfExists('companies');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'must_change_password']);
        });
    }
};
