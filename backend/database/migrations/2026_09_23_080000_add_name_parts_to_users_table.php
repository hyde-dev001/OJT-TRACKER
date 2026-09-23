<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('suffix', 20)->nullable()->after('last_name');
        });

        $allowedSuffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user) use ($allowedSuffixes): void {
                $parts = preg_split('/\s+/', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $suffix = null;

                if ($parts !== [] && in_array(end($parts), $allowedSuffixes, true)) {
                    $suffix = array_pop($parts);
                }

                $firstName = array_shift($parts) ?: null;
                $lastName = $parts !== [] ? implode(' ', $parts) : null;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'suffix' => $suffix,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name', 'suffix']);
        });
    }
};
