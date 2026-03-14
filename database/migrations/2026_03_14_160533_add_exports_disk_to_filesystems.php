<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure exports directory exists
        Storage::disk('local')->makeDirectory('exports');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clean up exports directory
        Storage::disk('local')->deleteDirectory('exports');
    }
};
