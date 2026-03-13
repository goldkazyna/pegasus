<?php

namespace Tests\Feature\Services;

use App\Services\ClaudeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClaudeServiceTest extends TestCase
{
    public function test_generates_post_from_tour_data(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Generated tour post text here'],
                ],
            ], 200),
        ]);

        $service = new ClaudeService();
        $tourData = [
            'hotel_name' => 'Hien Minh Bungalow',
            'stars' => 3,
            'country' => 'Вьетнам',
            'location' => 'Фукуок',
            'departure_city' => 'Алматы',
            'airline' => 'SCAT Airlines',
            'flight_out' => '2026-03-14 22:20',
            'flight_back' => '2026-03-20 09:30',
            'nights' => '5+1',
            'room_type' => 'Superior Bungalow',
            'meal_plan' => 'BB',
            'guests' => '2 взрослых',
            'price' => 740000,
            'amenities' => ['бассейн', 'Wi-Fi', 'парковка'],
        ];

        $result = $service->generatePost($tourData);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com/v1/messages')
                && $request->header('x-api-key')[0] !== ''
                && $request['model'] === config('claude.model');
        });
    }

    public function test_throws_on_api_error(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'rate_limited'], 429),
        ]);

        $service = new ClaudeService();

        $this->expectException(\RuntimeException::class);
        $service->generatePost(['hotel_name' => 'Test']);
    }
}
