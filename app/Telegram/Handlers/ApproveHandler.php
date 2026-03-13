<?php

namespace App\Telegram\Handlers;

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ApproveHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $post = DB::transaction(function () use ($id) {
            $post = Post::lockForUpdate()->find($id);

            if (!$post || $post->status !== 'draft') {
                return null;
            }

            $post->update(['status' => 'approved']);
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
    }
}
