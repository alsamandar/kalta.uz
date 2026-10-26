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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('groom_name');
            $table->string('bride_name');
            $table->dateTime('event_at');
            $table->string('venue');
            $table->string('address')->nullable();
            $table->string('map_url')->nullable();
            $table->text('message')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('locale', 2)->default('uz'); // language the invitation is written in
            $table->string('host_email')->nullable(); // receives RSVP notifications
            $table->date('rsvp_deadline')->nullable();
            $table->string('manage_token', 64)->unique(); // secret link for the hosts
            $table->timestamps();
        });

        Schema::create('invitation_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('token', 32)->unique(); // personal invitation link
            $table->string('status')->default('pending'); // pending | attending | declined
            $table->unsignedTinyInteger('party_size')->default(1);
            $table->text('note')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_guests');
        Schema::dropIfExists('invitations');
    }
};
