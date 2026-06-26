<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Topik / sub bab dalam mapel
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->string('topic');
            $table->string('slug');
            $table->foreignId('parent_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
            $table->unsignedTinyInteger('tingkat')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Jenis pertanyaan
        Schema::create('question_types', function (Blueprint $table) {
            $table->id();
            $table->string('question_type'); // Pilihan Ganda, Multi, Esai, Benar-Salah, Menjodohkan
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        // Bank soal
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('question');
            $table->foreignId('question_type_id')->nullable()->constrained('question_types')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->unsignedTinyInteger('tingkat')->nullable();
            $table->string('tingkat_kesulitan', 10)->default('sedang'); // mudah/sedang/sulit
            $table->text('media_url')->nullable();
            $table->string('media_type', 20)->nullable();
            $table->text('pembahasan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_guru_id')->nullable()->constrained('guru')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->text('option_text')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_type', 20)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();
        });

        // Quiz / Tes
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
            $table->foreignId('rombongan_belajar_id')->nullable()->constrained('rombongan_belajar')->nullOnDelete();
            $table->foreignId('tahun_ajaran_id')->nullable()->constrained('tahun_ajaran')->nullOnDelete();
            $table->foreignId('created_by_guru_id')->nullable()->constrained('guru')->nullOnDelete();
            $table->unsignedTinyInteger('tingkat')->nullable();
            $table->float('total_marks')->default(0);
            $table->float('pass_marks')->default(0);
            $table->unsignedInteger('max_attempts')->default(1);
            $table->boolean('is_published')->default(false);
            $table->string('cover_url')->nullable();
            $table->unsignedInteger('duration')->default(0); // menit
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_upto')->nullable();
            $table->boolean('randomize')->default(false);
            $table->boolean('show_score')->default(true);
            $table->boolean('require_session_token')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->float('marks')->default(1);
            $table->float('negative_marks')->default(0);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->boolean('is_done')->default(false);
            $table->text('order')->nullable();
            $table->timestamp('time_start')->nullable();
            $table->timestamp('time_end')->nullable();
            $table->float('score')->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->unsignedSmallInteger('empty_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->foreignId('question_option_id')->nullable()->constrained('question_options')->nullOnDelete();
            $table->text('answer_text')->nullable();
            $table->boolean('is_marked')->default(false);
            $table->boolean('is_correct')->nullable();
            $table->timestamps();
        });

        // Token sesi ujian
        Schema::create('session_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 12)->unique();
            $table->string('nama_sesi')->nullable();
            $table->foreignId('tahun_ajaran_id')->nullable()->constrained('tahun_ajaran')->nullOnDelete();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_upto')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Login attempt log untuk monitoring
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('guard')->default('siswa');
            $table->boolean('success')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('session_tokens');
        Schema::dropIfExists('quiz_attempt_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_types');
        Schema::dropIfExists('topics');
    }
};
