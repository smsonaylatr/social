<?php

use App\Models\Video;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('videos')) {
            return;
        }

        // Renumber existing videos starting from 1 in ascending upload/creation order
        $videos = Video::orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();
        $seq = 1;

        foreach ($videos as $video) {
            // Renumber if title is like "4. Video" or random hash
            if (preg_match('/^\d+\.\s*Video/i', trim($video->title)) || (strlen($video->title) >= 20 && ! str_contains($video->title, ' '))) {
                $video->update(['title' => "{$seq}. Video"]);
            }
            $seq++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed
    }
};
