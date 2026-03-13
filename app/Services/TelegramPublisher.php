<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;

class TelegramPublisher
{
    public function __construct(private Nutgram $bot) {}

    public function publishToChannel(string $text): int
    {
        if (mb_strlen($text) > 4096) {
            throw new \RuntimeException('Post text exceeds Telegram 4096 character limit');
        }

        $message = $this->bot->sendMessage(
            text: $text,
            chat_id: config('telegram.channel_id'),
        );

        return $message->message_id;
    }

    public function notifyUser(int $telegramId, string $text): void
    {
        $this->bot->sendMessage(
            text: $text,
            chat_id: $telegramId,
        );
    }
}
