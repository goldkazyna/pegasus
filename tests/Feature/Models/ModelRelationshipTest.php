<?php

namespace Tests\Feature\Models;

use App\Models\Post;
use App\Models\Tour;
use App\Models\TourBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_tour_batches(): void
    {
        $user = User::create([
            'telegram_id' => 123456789,
            'name' => 'Test User',
            'is_admin' => true,
        ]);

        $batch = TourBatch::create([
            'user_id' => $user->id,
            'source_url' => 'https://qui-quo.com/SG30-UT47',
        ]);

        $this->assertTrue($user->tourBatches->contains($batch));
    }

    public function test_tour_batch_has_many_tours(): void
    {
        $user = User::create([
            'telegram_id' => 123456789,
            'name' => 'Test User',
        ]);

        $batch = TourBatch::create([
            'user_id' => $user->id,
            'source_url' => 'https://qui-quo.com/SG30-UT47',
        ]);

        $tour = Tour::create([
            'batch_id' => $batch->id,
            'hotel_name' => 'Test Hotel',
            'stars' => 5,
            'country' => 'Вьетнам',
            'location' => 'Фукуок',
            'departure_city' => 'Алматы',
            'airline' => 'SCAT Airlines',
            'flight_out' => '2026-03-14 22:20:00',
            'flight_back' => '2026-03-20 09:30:00',
            'nights' => '5+1',
            'room_type' => 'Superior Bungalow',
            'meal_plan' => 'BB',
            'guests' => '2 взрослых',
            'price' => 740000,
        ]);

        $this->assertTrue($batch->tours->contains($tour));
    }

    public function test_tour_has_one_post(): void
    {
        $user = User::create([
            'telegram_id' => 123456789,
            'name' => 'Test User',
        ]);

        $batch = TourBatch::create([
            'user_id' => $user->id,
            'source_url' => 'https://qui-quo.com/SG30-UT47',
        ]);

        $tour = Tour::create([
            'batch_id' => $batch->id,
            'hotel_name' => 'Test Hotel',
            'stars' => 5,
            'country' => 'Вьетнам',
            'location' => 'Фукуок',
            'departure_city' => 'Алматы',
            'airline' => 'SCAT Airlines',
            'flight_out' => '2026-03-14 22:20:00',
            'flight_back' => '2026-03-20 09:30:00',
            'nights' => '5+1',
            'room_type' => 'Superior Bungalow',
            'meal_plan' => 'BB',
            'guests' => '2 взрослых',
            'price' => 740000,
        ]);

        $post = Post::create([
            'tour_id' => $tour->id,
            'user_id' => $user->id,
            'generated_text' => 'Test post text',
            'status' => 'draft',
        ]);

        $this->assertTrue($tour->post->is($post));
        $this->assertTrue($post->tour->is($tour));
    }
}
