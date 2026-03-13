<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\Tour;
use App\Models\TourBatch;
use App\Services\ClaudeService;
use App\Services\QuiQuoParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ProcessTourBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public TourBatch $batch,
        public int $chatId,
    ) {}

    public function handle(QuiQuoParser $parser, ClaudeService $claude, Nutgram $bot): void
    {
        try {
            $toursData = $parser->fetchAndParse($this->batch->source_url);
        } catch (\Throwable) {
            $bot->sendMessage(
                text: 'Не удалось загрузить страницу, попробуйте позже.',
                chat_id: $this->chatId,
            );
            return;
        }

        if (empty($toursData)) {
            $bot->sendMessage(
                text: 'Туры не найдены по этой ссылке.',
                chat_id: $this->chatId,
            );
            return;
        }

        $bot->sendMessage(
            text: "Найдено " . count($toursData) . " туров. Генерирую посты...",
            chat_id: $this->chatId,
        );

        foreach ($toursData as $tourData) {
            $tour = Tour::create(array_merge($tourData, [
                'batch_id' => $this->batch->id,
                'raw_data' => $tourData,
            ]));

            try {
                $generatedText = $claude->generatePost($tourData);
            } catch (\Throwable) {
                $bot->sendMessage(
                    text: "Не удалось сгенерировать пост для {$tour->hotel_name}.",
                    chat_id: $this->chatId,
                );
                continue;
            }

            $post = Post::create([
                'tour_id' => $tour->id,
                'user_id' => $this->batch->user_id,
                'generated_text' => $generatedText,
                'status' => 'draft',
            ]);

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('✅ Одобрить', callback_data: "approve:{$post->id}"),
                    InlineKeyboardButton::make('❌ Отклонить', callback_data: "reject:{$post->id}"),
                )
                ->addRow(
                    InlineKeyboardButton::make('🔄 Перегенерировать', callback_data: "regenerate:{$post->id}"),
                );

            $bot->sendMessage(
                text: $generatedText,
                chat_id: $this->chatId,
                reply_markup: $keyboard,
            );
        }

        $bot->sendMessage(
            text: 'Все туры обработаны!',
            chat_id: $this->chatId,
        );
    }
}
