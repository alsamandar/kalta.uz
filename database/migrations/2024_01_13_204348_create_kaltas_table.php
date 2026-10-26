<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kaltas', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('description')->nullable();
            $table->string('name')->nullable()->default("unnamed kalta");
            $table->foreignId('user_id');
            $table->morphs('kaltaable');
            $table->string('ip');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kaltas');
    }
};
