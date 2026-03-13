<?php

namespace App\Telegram\Handlers;

use App\Models\Post;
use App\Services\ClaudeService;
use Illuminate\Support\Facades\DB;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class RegenerateHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $post = DB::transaction(function () use ($id) {
            $post = Post::with('tour')->lockForUpdate()->find($id);

            if (!$post || $post->status !== 'draft') {
                return null;
            }

            if (!$post->canRegenerate()) {
                return false;
            }

            return $post;
        });

        if ($post === null) {
            $bot->answerCallbackQuery(text: 'Пост уже обработан.');
            return;
        }

        if ($post === false) {
            $bot->answerCallbackQuery(
                text: 'Лимит перегенераций исчерпан (макс ' . config('claude.max_regenerations') . ').',
                show_alert: true,
            );
            return;
        }

        $bot->answerCallbackQuery(text: 'Генерирую новый вариант...');

        $claude = app(ClaudeService::class);

        try {
            $newText = $claude->generatePost($post->tour->raw_data ?? $post->tour->toArray());
        } catch (\Throwable) {
            $bot->sendMessage('Не удалось перегенерировать пост, попробуйте позже.');
            return;
        }

        $post->update([
            'generated_text'     => $newText,
            'regeneration_count' => $post->regeneration_count + 1,
        ]);

        $remaining = config('claude.max_regenerations') - $post->regeneration_count;

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('✅ Одобрить', callback_data: "approve:{$post->id}"),
                InlineKeyboardButton::make('❌ Отклонить', callback_data: "reject:{$post->id}"),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    "🔄 Перегенерировать ({$remaining} осталось)",
                    callback_data: "regenerate:{$post->id}"
                ),
            );

        $bot->editMessageText(
            text: $newText,
            message_id: $bot->callbackQuery()->message->message_id,
            reply_markup: $keyboard,
        );
    }
}
