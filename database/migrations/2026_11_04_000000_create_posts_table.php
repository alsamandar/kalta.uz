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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('author', 100)->nullable();
            $table->mediumText('body'); // cleaned HTML (see App\Support\PostHtml)
            $table->string('excerpt', 300)->default(''); // the first words, for search results and link previews
            $table->string('image')->nullable(); // the first picture in the post, "media/posts/..."
            $table->unsignedInteger('words')->default(0);
            $table->string('edit_token', 64)->unique(); // lets the author edit from the browser they wrote it in
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
