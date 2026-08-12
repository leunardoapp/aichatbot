<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! isDBDriverSQLite()) {
            $sqlFilePath = resource_path('dev_tools/extensions/social_media.sql');
            if (! file_exists($sqlFilePath)) {
                throw new \RuntimeException("SQL file not found: {$sqlFilePath}");
            }
            $sql = file_get_contents($sqlFilePath);
            DB::unprepared($sql);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
