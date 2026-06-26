<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->longText('value')->nullable();
            $t->string('type', 20)->default('text'); // text | json | file | color | bool
            $t->string('group', 50)->default('umum');
            $t->string('label', 150)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
