<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $t) {
            if (! Schema::hasColumn('quizzes', 'protection_enabled')) {
                $t->boolean('protection_enabled')->default(true);
            }
            if (! Schema::hasColumn('quizzes', 'max_violations')) {
                $t->unsignedSmallInteger('max_violations')->default(5);
            }
        });

        Schema::table('quiz_attempts', function (Blueprint $t) {
            if (! Schema::hasColumn('quiz_attempts', 'is_blocked')) {
                $t->boolean('is_blocked')->default(false);
            }
            if (! Schema::hasColumn('quiz_attempts', 'blocked_at')) {
                $t->timestamp('blocked_at')->nullable();
            }
            if (! Schema::hasColumn('quiz_attempts', 'blocked_reason')) {
                $t->string('blocked_reason', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $t) {
            $t->dropColumn(['protection_enabled', 'max_violations']);
        });
        Schema::table('quiz_attempts', function (Blueprint $t) {
            $t->dropColumn(['is_blocked', 'blocked_at', 'blocked_reason']);
        });
    }
};
