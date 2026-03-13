<?php

namespace App\Console\Commands;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use Illuminate\Console\Command;

class CheckStuckPostsCommand extends Command
{
    protected $signature = 'posts:check-stuck';
    protected $description = 'Find and re-dispatch stuck scheduled posts';

    public function handle(): int
    {
        $stuckPosts = Post::with('tour')->where('status', 'scheduled')
            ->where('publish_at', '<', now())
            ->get();

        foreach ($stuckPosts as $post) {
            PublishPostJob::dispatch($post);
            $this->info("Re-dispatched post #{$post->id}: {$post->tour->hotel_name}");
        }

        $this->info("Found {$stuckPosts->count()} stuck post(s).");

        return self::SUCCESS;
    }
}
