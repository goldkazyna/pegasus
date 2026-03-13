<?php

namespace Tests\Feature\Console;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use App\Models\Tour;
use App\Models\TourBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckStuckPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createPost(string $status, $publishAt): Post
    {
        $user = User::firstOrCreate(
            ['telegram_id' => 123],
            ['name' => 'Test']
        );
        $batch = TourBatch::create(['user_id' => $user->id, 'source_url' => 'https://qui-quo.com/test']);
        $tour = Tour::create([
            'batch_id' => $batch->id,
            'hotel_name' => 'Test Hotel',
            'stars' => 4,
            'country' => 'Test',
            'location' => 'Test',
            'departure_city' => 'Алматы',
            'airline' => 'Test',
            'flight_out' => now(),
            'flight_back' => now()->addDays(7),
            'nights' => '7',
            'room_type' => 'Standard',
            'meal_plan' => 'AI',
            'guests' => '2',
            'price' => 1000000,
        ]);

        return Post::create([
            'tour_id' => $tour->id,
            'user_id' => $user->id,
            'generated_text' => 'Test post',
            'status' => $status,
            'publish_at' => $publishAt,
        ]);
    }

    public function test_dispatches_jobs_for_stuck_posts(): void
    {
        Queue::fake();

        $this->createPost('scheduled', now()->subHour());

        $this->artisan('posts:check-stuck')->assertSuccessful();

        Queue::assertPushed(PublishPostJob::class, 1);
    }

    public function test_ignores_future_scheduled_posts(): void
    {
        Queue::fake();

        $this->createPost('scheduled', now()->addHour());

        $this->artisan('posts:check-stuck')->assertSuccessful();

        Queue::assertNotPushed(PublishPostJob::class);
    }

    public function test_ignores_non_scheduled_posts(): void
    {
        Queue::fake();

        $this->createPost('draft', now()->subHour());

        $this->artisan('posts:check-stuck')->assertSuccessful();

        Queue::assertNotPushed(PublishPostJob::class);
    }
}
