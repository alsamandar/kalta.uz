<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Links with tracking parameters or map coordinates easily pass 255 characters.
     */
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE shorts MODIFY long_url TEXT NOT NULL');
        }
    }

    /**
     * Reverse the migrations (fails on MySQL if a longer link is stored by then).
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE shorts MODIFY long_url VARCHAR(255) NOT NULL');
        }
    }
};
