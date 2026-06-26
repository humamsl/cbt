<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $t) {
            if (! Schema::hasColumn('quizzes', 'randomize_options')) {
                $t->boolean('randomize_options')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $t) {
            $t->dropColumn('randomize_options');
        });
    }
};
