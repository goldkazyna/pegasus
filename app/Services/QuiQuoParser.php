<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class QuiQuoParser
{
    public function fetchAndParse(string $url): array
    {
        $response = Http::timeout(15)->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch URL: {$url} (status: {$response->status()})");
        }

        return $this->parseHtml($response->body());
    }

    public function parseHtml(string $html): array
    {
        $crawler = new Crawler($html);
        $tours = [];

        $crawler->filter('section.tour')->each(function (Crawler $node) use (&$tours) {
            try {
                $tour = $this->extractTourData($node);
                if ($tour !== null) {
                    $tours[] = $tour;
                }
            } catch (\Throwable) {
                // Skip malformed tour cards
            }
        });

        return $tours;
    }

    private function extractTourData(Crawler $node): ?array
    {
        // Hotel name: "1. Hien Minh Bungalow 3*" -> extract name and stars
        $rawName = $this->text($node, '.hotel .info .name noindex a');
        if (empty($rawName)) {
            return null;
        }

        // Parse "1. Hien Minh Bungalow 3*" -> name="Hien Minh Bungalow", stars=3
        $hotelName = $rawName;
        $stars = 0;

        // Remove leading number: "1. "
        $hotelName = preg_replace('/^\d+\.\s*/', '', $hotelName);

        // Extract stars from end: " 3*" or " 5*"
        if (preg_match('/\s(\d)\*\s*$/', $hotelName, $m)) {
            $stars = (int) $m[1];
            $hotelName = preg_replace('/\s\d\*\s*$/', '', $hotelName);
        }

        // Country and location: "Вьетнам, Фукуок" -> country="Вьетнам", location="Фукуок"
        $countryText = $this->text($node, '.hotel .info .country');
        $country = $countryText;
        $location = '';
        if (str_contains($countryText, ',')) {
            [$country, $location] = array_map('trim', explode(',', $countryText, 2));
        }

        // Departure city: "Начало тура: из Алматы" -> "Алматы"
        $depText = $this->text($node, '.depcity');
        $departureCity = preg_replace('/^.*из\s+/u', '', $depText);

        // Nights: "5+1 ночей" -> "5+1"
        $nightsText = $this->text($node, '.nights');
        $nights = preg_replace('/\s*ноч.*$/u', '', $nightsText);

        // Meal plan: "BB - Только завтрак"
        $mealPlan = $this->text($node, '.board span');

        // Room: "superior bungalow, 2 взрослых"
        $roomText = $this->text($node, '.room span');
        $roomType = $roomText;
        $guests = '';
        // Split room and guests on last comma before number of adults
        if (preg_match('/^(.+),\s*(\d+\s+взрослы.*)$/u', $roomText, $m)) {
            $roomType = trim($m[1]);
            $guests = trim($m[2]);
        }

        // Airline from forward flight
        $airline = $this->text($node, '.flight-direction.forward .flight-number');

        // Flight times
        $flightOutDep = $this->text($node, '.flight-direction.forward .flight-departure');
        $flightBackDep = $this->text($node, '.flight-direction.backward .flight-departure');

        // Parse flight datetime: "14 мар 22:20 Алматы" -> datetime string
        $flightOut = $this->parseFlightDateTime($flightOutDep);
        $flightBack = $this->parseFlightDateTime($flightBackDep);

        // Price: "740 000 KZT" -> 740000
        $priceText = $this->text($node, '.price');
        $price = (int) preg_replace('/[^\d]/', '', $priceText);

        // Amenities
        $amenities = [];
        $node->filter('.amenities .amenity')->each(function (Crawler $el) use (&$amenities) {
            $text = trim($el->text(''));
            if ($text !== '') {
                $amenities[] = $text;
            }
        });

        return [
            'hotel_name' => $hotelName,
            'stars' => $stars,
            'country' => $country,
            'location' => $location,
            'departure_city' => $departureCity,
            'airline' => $airline,
            'flight_out' => $flightOut,
            'flight_back' => $flightBack,
            'nights' => $nights,
            'room_type' => $roomType,
            'meal_plan' => $mealPlan,
            'guests' => $guests,
            'price' => $price,
            'amenities' => $amenities,
        ];
    }

    private function text(Crawler $node, string $selector): string
    {
        try {
            return trim($node->filter($selector)->first()->text(''));
        } catch (\Throwable) {
            return '';
        }
    }

    private function parseFlightDateTime(string $text): ?string
    {
        // Input: "14 мар 22:20 Алматы" or "20 мар 09:30 Фукуок"
        $months = [
            'янв' => '01', 'фев' => '02', 'мар' => '03', 'апр' => '04',
            'май' => '05', 'мая' => '05', 'июн' => '06', 'июл' => '07',
            'авг' => '08', 'сен' => '09', 'окт' => '10', 'ноя' => '11', 'дек' => '12',
        ];

        if (preg_match('/(\d{1,2})\s+(янв|фев|мар|апр|май|мая|июн|июл|авг|сен|окт|ноя|дек)\s+(\d{2}:\d{2})/u', $text, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = $months[$m[2]] ?? '01';
            $time = $m[3];
            $year = date('Y'); // Current year
            return "{$year}-{$month}-{$day} {$time}:00";
        }

        return null;
    }
}
