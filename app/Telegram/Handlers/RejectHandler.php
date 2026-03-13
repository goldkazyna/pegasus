<?php

namespace App\Telegram\Handlers;

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use SergiX44\Nutgram\Nutgram;

class RejectHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $post = DB::transaction(function () use ($id) {
            $post = Post::lockForUpdate()->find($id);

            if (!$post || !in_array($post->status, ['draft', 'approved'])) {
                return null;
            }

            $post->update(['status' => 'rejected']);
            return $post;
        });

        if (!$post) {
            $bot->answerCallbackQuery(text: 'Пост уже обработан.');
            return;
        }

        $bot->answerCallbackQuery(text: 'Пост отклонён.');

        $bot->deleteMessage(
            message_id: $bot->callbackQuery()->message->message_id,
        );
    }
}
