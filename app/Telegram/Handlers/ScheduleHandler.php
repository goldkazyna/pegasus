<?php

namespace App\Telegram\Handlers;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class ScheduleHandler
{
    public function __invoke(Nutgram $bot, string $id, string $option): void
    {
        Log::info("ScheduleHandler: received schedule for post {$id}, option={$option}");

        $almatyNow = now()->timezone('Asia/Almaty');

        $publishAt = match ($option) {
            'now'         => $almatyNow->copy(),
            '1h'         => $almatyNow->copy()->addHour(),
            '3h'         => $almatyNow->copy()->addHours(3),
            'today_18'   => $almatyNow->copy()->setTime(18, 0),
            'tomorrow_10' => $almatyNow->copy()->addDay()->setTime(10, 0),
            default      => null,
        };

        if (!$publishAt) {
            Log::warning("ScheduleHandler: unknown option '{$option}' for post {$id}");
            $bot->answerCallbackQuery(text: 'Неизвестная опция.');
            return;
        }

        if ($publishAt->isPast()) {
            $publishAt->addDay();
        }

        $publishAtUtc = $publishAt->utc();

        Log::info("ScheduleHandler: post {$id} will publish at {$publishAtUtc} (UTC), option={$option}");

        $post = DB::transaction(function () use ($id, $publishAtUtc) {
            $post = Post::lockForUpdate()->find($id);

            if (!$post) {
                Log::warning("ScheduleHandler: post {$id} not found in DB");
                return null;
            }

            if ($post->status !== 'approved') {
                Log::warning("ScheduleHandler: post {$id} status is '{$post->status}', expected 'approved'");
                return null;
            }

            $post->update([
                'status'     => 'scheduled',
                'publish_at' => $publishAtUtc,
            ]);

            Log::info("ScheduleHandler: post {$id} status changed to scheduled");
            return $post;
        });

        if (!$post) {
            $bot->answerCallbackQuery(text: 'Пост уже запланирован или обработан.');
            return;
        }

        if ($option === 'now') {
            Log::info("ScheduleHandler: dispatching PublishPostJob immediately for post {$id}");
            PublishPostJob::dispatch($post);
        } else {
            Log::info("ScheduleHandler: dispatching PublishPostJob with delay for post {$id}");
            PublishPostJob::dispatch($post)->delay($publishAtUtc);
        }

        $bot->answerCallbackQuery();
        $bot->editMessageReplyMarkup(
            message_id: $bot->callbackQuery()->message->message_id,
        );
        $bot->sendMessage(
            "📅 Пост запланирован на {$publishAt->format('d.m.Y H:i')} (Алматы)"
        );

        Log::info("ScheduleHandler: done for post {$id}");
    }

    public function promptCustom(Nutgram $bot, string $id): void
    {
        $bot->answerCallbackQuery();
        $bot->sendMessage(
            "Введите дату и время публикации в формате:\n"
            . "`{$id} ДД.ММ ЧЧ:ММ`\n\n"
            . "Например: `{$id} 15.03 18:00`",
            parse_mode: 'Markdown',
        );
    }
}
