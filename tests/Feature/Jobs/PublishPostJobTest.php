<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use App\Models\Tour;
use App\Models\TourBatch;
use App\Models\User;
use App\Services\TelegramPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishPostJobTest extends TestCase
{
    use RefreshDatabase;

    private function createTestPost(string $status = 'scheduled'): Post
    {
        $user = User::create([
            'telegram_id' => 123456789,
            'name' => 'Admin',
            'is_admin' => true,
        ]);

        $batch = TourBatch::create([
            'user_id' => $user->id,
            'source_url' => 'https://qui-quo.com/test',
        ]);

        $tour = Tour::create([
            'batch_id' => $batch->id,
            'hotel_name' => 'Test Hotel',
            'stars' => 5,
            'country' => 'Test',
            'location' => 'Test',
            'departure_city' => 'Алматы',
            'airline' => 'Test Air',
            'flight_out' => now(),
            'flight_back' => now()->addDays(7),
            'nights' => '7',
            'room_type' => 'Standard',
            'meal_plan' => 'BB',
            'guests' => '2 взрослых',
            'price' => 500000,
        ]);

        return Post::create([
            'tour_id' => $tour->id,
            'user_id' => $user->id,
            'generated_text' => 'Test post for publishing',
            'status' => $status,
            'publish_at' => now()->subMinute(),
        ]);
    }

    public function test_publishes_scheduled_post(): void
    {
        $post = $this->createTestPost('scheduled');

        $publisher = $this->mock(TelegramPublisher::class);
        $publisher->shouldReceive('publishToChannel')
            ->once()
            ->with($post->generated_text)
            ->andReturn(12345);
        $publisher->shouldReceive('notifyUser')
            ->once();

        $job = new PublishPostJob($post);
        $job->handle($publisher);

        $post->refresh();
        $this->assertEquals('published', $post->status);
        $this->assertEquals(12345, $post->telegram_message_id);
        $this->assertNotNull($post->published_at);
    }

    public function test_skips_non_scheduled_post(): void
    {
        $post = $this->createTestPost('published');

        $publisher = $this->mock(TelegramPublisher::class);
        $publisher->shouldNotReceive('publishToChannel');

        $job = new PublishPostJob($post);
        $job->handle($publisher);
    }
}
