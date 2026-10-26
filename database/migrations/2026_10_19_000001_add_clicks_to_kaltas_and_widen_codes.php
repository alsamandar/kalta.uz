<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kaltas', function (Blueprint $table) {
            $table->unsignedInteger('clicks')->default(0)->after('ip');
            $table->index('user_id');
        });

        // Text snippets were limited to 255 characters
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE codes MODIFY content MEDIUMTEXT NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kaltas', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('clicks');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE codes MODIFY content VARCHAR(255) NOT NULL');
        }
    }
};
