<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $t) {
            if (! Schema::hasColumn('questions', 'correct_answer_text')) {
                $t->text('correct_answer_text')->nullable(); // untuk fill-the-blank (boleh JSON multi-jawaban)
            }
            if (! Schema::hasColumn('questions', 'case_sensitive')) {
                $t->boolean('case_sensitive')->default(false);
            }
        });

        Schema::table('question_options', function (Blueprint $t) {
            if (! Schema::hasColumn('question_options', 'is_left_side')) {
                $t->boolean('is_left_side')->default(true); // penjodohan: 1 = kiri, 0 = kanan
            }
            if (! Schema::hasColumn('question_options', 'pair_group')) {
                $t->unsignedSmallInteger('pair_group')->nullable(); // penjodohan: pasangan kiri-kanan dgn id sama
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $t) {
            $t->dropColumn(['correct_answer_text', 'case_sensitive']);
        });
        Schema::table('question_options', function (Blueprint $t) {
            $t->dropColumn(['is_left_side', 'pair_group']);
        });
    }
};
