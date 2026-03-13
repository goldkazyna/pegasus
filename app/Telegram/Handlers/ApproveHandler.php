<?php

namespace App\Telegram\Handlers;

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ApproveHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Log::info("ApproveHandler: received approve for post {$id}");

        $post = DB::transaction(function () use ($id) {
            $post = Post::lockForUpdate()->find($id);

            if (!$post) {
                Log::warning("ApproveHandler: post {$id} not found");
                return null;
            }

            if ($post->status !== 'draft') {
                Log::warning("ApproveHandler: post {$id} status is '{$post->status}', expected 'draft'");
                return null;
            }

            $post->update(['status' => 'approved']);
            Log::info("ApproveHandler: post {$id} status changed to approved");
            return $post;
        });

        if (!$post) {
            $bot->answerCallbackQuery(text: 'Пост уже обработан.');
            return;
        }

        $bot->answerCallbackQuery(text: 'Одобрено! Выберите время публикации.');

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    '🚀 Сейчас',
                    callback_data: "schedule:{$post->id}:now"
                ),
                InlineKeyboardButton::make(
                    'Через 1 час',
                    callback_data: "schedule:{$post->id}:1h"
                ),
                InlineKeyboardButton::make(
                    'Через 3 часа',
                    callback_data: "schedule:{$post->id}:3h"
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    "Сегодня 18:00",
                    callback_data: "schedule:{$post->id}:today_18"
                ),
                InlineKeyboardButton::make(
                    "Завтра 10:00",
                    callback_data: "schedule:{$post->id}:tomorrow_10"
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    '✏️ Ввести вручную',
                    callback_data: "schedule_custom:{$post->id}"
                ),
            );

        $bot->editMessageReplyMarkup(
            message_id: $bot->callbackQuery()->message->message_id,
            reply_markup: $keyboard,
        );

        Log::info("ApproveHandler: schedule keyboard sent for post {$id}");
    }
}
