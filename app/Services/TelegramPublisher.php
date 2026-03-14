<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;

class TelegramPublisher
{
    public function __construct(private Nutgram $bot) {}

    public function publishToChannel(string $text, ?string $imageUrl = null): int
    {
        $channelId = config('telegram.channel_id');

        if ($imageUrl) {
            // Photo caption limit is 1024 chars
            $caption = mb_strlen($text) > 1024 ? mb_substr($text, 0, 1021) . '...' : $text;

            $message = $this->bot->sendPhoto(
                photo: $imageUrl,
                caption: $caption,
                chat_id: $channelId,
            );
        } else {
            if (mb_strlen($text) > 4096) {
                throw new \RuntimeException('Post text exceeds Telegram 4096 character limit');
            }

            $message = $this->bot->sendMessage(
                text: $text,
                chat_id: $channelId,
            );
        }

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
