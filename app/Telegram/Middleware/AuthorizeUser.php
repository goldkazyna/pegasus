<?php

namespace App\Telegram\Middleware;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class AuthorizeUser
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $telegramId = $bot->userId();

        $user = User::where('telegram_id', $telegramId)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            // Auto-create admin on first use
            if ($telegramId === config('telegram.admin_id')) {
                $user = User::create([
                    'telegram_id' => $telegramId,
                    'name' => $bot->user()->first_name ?? 'Admin',
                    'is_admin' => true,
                ]);
            } else {
                $bot->sendMessage('У вас нет доступа к этому боту.');
                return;
            }
        }

        // Store user in bot data for handlers
        $bot->set('db_user', $user);
        $next($bot);
    }
}
