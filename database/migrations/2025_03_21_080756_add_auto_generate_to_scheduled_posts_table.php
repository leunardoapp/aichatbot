<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('scheduled_posts', 'auto_generate')) {
            return;
        }

        Schema::table('scheduled_posts', static function (Blueprint $table) {
            $table->boolean('auto_generate')->default(false)->nullable();
        });
    }

    public function down(): void {}
};
