<?php

namespace App\Telegram;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use App\Telegram\Handlers\AdminHandler;
use App\Telegram\Handlers\ApproveHandler;
use App\Telegram\Handlers\LinkHandler;
use App\Telegram\Handlers\RegenerateHandler;
use App\Telegram\Handlers\RejectHandler;
use App\Telegram\Handlers\ScheduleHandler;
use App\Telegram\Middleware\AuthorizeUser;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use SergiX44\Nutgram\Nutgram;

class BotServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Skip handler registration when no token is configured (e.g. CLI, tests).
        if (empty(config('nutgram.token'))) {
            return;
        }

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);

        $bot->middleware(AuthorizeUser::class);

        // Handle qui-quo links in message text.
        // The pattern is a plain regex (no {param} tokens); the URL is read from
        // $bot->message()->text inside the handler itself.
        $bot->onText('https?://qui-quo\.com/.+', LinkHandler::class);

        // Callback query handlers — {id} and {option} are named capture groups
        // extracted by nutgram and injected as positional arguments.
        $bot->onCallbackQueryData('approve:{id}', ApproveHandler::class);
        $bot->onCallbackQueryData('reject:{id}', RejectHandler::class);
        $bot->onCallbackQueryData('regenerate:{id}', RegenerateHandler::class);
        $bot->onCallbackQueryData('schedule:{id}:{option}', ScheduleHandler::class);
        $bot->onCallbackQueryData('schedule_custom:{id}', [ScheduleHandler::class, 'promptCustom']);

        // Manual time input: "<post_id> DD.MM HH:MM"
        // nutgram injects {id}, {date}, {time} as positional string arguments.
        $bot->onText('{id} {date} {time}', function (Nutgram $bot, string $id, string $date, string $time) {
            if (!preg_match('/^\d+$/', $id) || !preg_match('/^\d{2}\.\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
                return;
            }

            $post = Post::find($id);
            if (!$post || $post->status !== 'approved') {
                $bot->sendMessage('Пост не найден или уже обработан.');
                return;
            }

            if ($post->user_id !== $bot->get('db_user')->id) {
                $bot->sendMessage('Этот пост не ваш.');
                return;
            }

            try {
                $year = now()->timezone('Asia/Almaty')->year;
                $publishAt = Carbon::createFromFormat(
                    'd.m.Y H:i',
                    "{$date}.{$year} {$time}",
                    'Asia/Almaty'
                );
            } catch (\Throwable) {
                $bot->sendMessage('Неверная дата. Формат: ID ДД.ММ ЧЧ:ММ');
                return;
            }

            $publishAtUtc = $publishAt->utc();
            $post->update([
                'status'     => 'scheduled',
                'publish_at' => $publishAtUtc,
            ]);

            PublishPostJob::dispatch($post)->delay($publishAtUtc);
            $bot->sendMessage("📅 Пост запланирован на {$publishAt->format('d.m.Y H:i')} (Алматы)");
        });

        // Admin commands — nutgram prepends "/" to the command name automatically.
        // {telegram_id} and {name} are injected as positional arguments.
        $bot->onCommand('add {telegram_id} {name}', AdminHandler::class);
        $bot->onCommand('remove {telegram_id}', [AdminHandler::class, 'remove']);

        // Start command
        $bot->onCommand('start', function (Nutgram $bot) {
            $bot->sendMessage('Привет! Отправь мне ссылку с qui-quo.com и я сгенерирую посты для канала.');
        });
    }
}
