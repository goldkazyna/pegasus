<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class AdminHandler
{
    public function __invoke(Nutgram $bot, string $telegram_id, string $name): void
    {
        /** @var User $admin */
        $admin = $bot->get('db_user');

        if (!$admin->is_admin) {
            $bot->sendMessage('Только администратор может добавлять менеджеров.');
            return;
        }

        User::updateOrCreate(
            ['telegram_id' => (int) $telegram_id],
            ['name' => $name, 'is_active' => true],
        );

        $bot->sendMessage("Менеджер {$name} (ID: {$telegram_id}) добавлен.");
    }

    public function remove(Nutgram $bot, string $telegram_id): void
    {
        /** @var User $admin */
        $admin = $bot->get('db_user');

        if (!$admin->is_admin) {
            $bot->sendMessage('Только администратор может удалять менеджеров.');
            return;
        }

        $user = User::where('telegram_id', (int) $telegram_id)->first();

        if (!$user) {
            $bot->sendMessage('Менеджер не найден.');
            return;
        }

        $user->update(['is_active' => false]);
        $bot->sendMessage("Менеджер {$user->name} деактивирован.");
    }
}
