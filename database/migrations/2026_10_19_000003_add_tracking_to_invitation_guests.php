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
        Schema::table('invitation_guests', function (Blueprint $table) {
            $table->string('sent_via', 12)->nullable()->after('sent_at'); // email | telegram | whatsapp | share | copy | link | manual
            $table->timestamp('opened_at')->nullable()->after('responded_at'); // first time the guest opened their personal link
            $table->unsignedSmallInteger('views')->default(0)->after('opened_at');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->text('share_message')->nullable()->after('message'); // the hosts' own wording for the message they send to guests
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('share_message');
        });

        Schema::table('invitation_guests', function (Blueprint $table) {
            $table->dropColumn(['sent_via', 'opened_at', 'views']);
        });
    }
};
