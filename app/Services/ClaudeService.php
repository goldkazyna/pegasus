<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ClaudeService
{
    public function generatePost(array $tourData): string
    {
        $userMessage = $this->buildUserMessage($tourData);

        $response = Http::withHeaders([
            'x-api-key' => config('claude.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('claude.model'),
            'max_tokens' => 2048,
            'system' => config('claude.system_prompt'),
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Claude API error: {$response->status()} — " . $response->body()
            );
        }

        $text = $response->json('content.0.text', '');

        if (empty($text)) {
            throw new \RuntimeException('Claude API returned empty response');
        }

        return $text;
    }

    private function buildUserMessage(array $tour): string
    {
        $amenitiesList = '';
        if (!empty($tour['amenities'])) {
            $amenitiesList = implode(', ', $tour['amenities']);
        }

        $pricePerPerson = '';
        if (!empty($tour['price']) && $tour['price'] > 0) {
            $pp = number_format(intdiv($tour['price'], 2), 0, '', ' ');
            $total = number_format($tour['price'], 0, '', ' ');
            $pricePerPerson = "Цена: {$total} тенге на двоих (~{$pp} тенге с человека)";
        }

        $hotelName    = $tour['hotel_name'] ?? '';
        $stars        = $tour['stars'] ?? '';
        $country      = $tour['country'] ?? '';
        $location     = $tour['location'] ?? '';
        $departureCity = $tour['departure_city'] ?? '';
        $airline      = $tour['airline'] ?? '';
        $flightOut    = $tour['flight_out'] ?? '';
        $flightBack   = $tour['flight_back'] ?? '';
        $nights       = $tour['nights'] ?? '';
        $roomType     = $tour['room_type'] ?? '';
        $mealPlan     = $tour['meal_plan'] ?? '';
        $guests       = $tour['guests'] ?? '';

        return <<<MSG
        Сгенерируй продающий пост для этого тура:

        Отель: {$hotelName}
        Звёзды: {$stars}
        Страна: {$country}
        Курорт: {$location}
        Город вылета: {$departureCity}
        Авиакомпания: {$airline}
        Вылет туда: {$flightOut}
        Вылет обратно: {$flightBack}
        Ночей: {$nights}
        Номер: {$roomType}
        Питание: {$mealPlan}
        Размещение: {$guests}
        {$pricePerPerson}
        Удобства: {$amenitiesList}
        MSG;
    }
}
