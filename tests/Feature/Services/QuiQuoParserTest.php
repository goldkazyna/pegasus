<?php

namespace Tests\Feature\Services;

use App\Services\QuiQuoParser;
use Tests\TestCase;

class QuiQuoParserTest extends TestCase
{
    public function test_parses_tours_from_html(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/quiquo-sample.html'));
        $parser = new QuiQuoParser();
        $tours = $parser->parseHtml($html);

        $this->assertIsArray($tours);
        $this->assertGreaterThan(0, count($tours));

        // Check first tour structure
        $first = $tours[0];
        $this->assertArrayHasKey('hotel_name', $first);
        $this->assertArrayHasKey('stars', $first);
        $this->assertArrayHasKey('country', $first);
        $this->assertArrayHasKey('location', $first);
        $this->assertArrayHasKey('price', $first);
        $this->assertArrayHasKey('nights', $first);
        $this->assertArrayHasKey('meal_plan', $first);
        $this->assertArrayHasKey('airline', $first);
        $this->assertArrayHasKey('departure_city', $first);
        $this->assertArrayHasKey('room_type', $first);
        $this->assertArrayHasKey('guests', $first);
        $this->assertArrayHasKey('flight_out', $first);
        $this->assertArrayHasKey('flight_back', $first);
        $this->assertArrayHasKey('amenities', $first);

        // Verify first tour data (Hien Minh Bungalow)
        $this->assertEquals('Hien Minh Bungalow', $first['hotel_name']);
        $this->assertEquals(3, $first['stars']);
        $this->assertEquals('Вьетнам', $first['country']);
        $this->assertEquals('Фукуок', $first['location']);
        $this->assertEquals(740000, $first['price']);
        $this->assertStringContainsString('5+1', $first['nights']);
        $this->assertEquals('Алматы', $first['departure_city']);
        $this->assertStringContainsString('SCAT', $first['airline']);
    }

    public function test_returns_empty_array_for_empty_html(): void
    {
        $parser = new QuiQuoParser();
        $tours = $parser->parseHtml('<html><body></body></html>');

        $this->assertIsArray($tours);
        $this->assertCount(0, $tours);
    }

    public function test_parses_multiple_tours(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/quiquo-sample.html'));
        $parser = new QuiQuoParser();
        $tours = $parser->parseHtml($html);

        // The fixture has 11 tours
        $this->assertCount(11, $tours);

        // Check last tour has valid data
        $last = end($tours);
        $this->assertNotEmpty($last['hotel_name']);
        $this->assertGreaterThan(0, $last['price']);
    }
}
