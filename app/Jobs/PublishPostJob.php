<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\TelegramPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Post $post) {}

    public function handle(TelegramPublisher $publisher): void
    {
        $this->post->refresh();

        if ($this->post->status !== 'scheduled') {
            return;
        }

        $messageId = $publisher->publishToChannel($this->post->generated_text);

        $this->post->update([
            'status' => 'published',
            'published_at' => now(),
            'telegram_message_id' => $messageId,
        ]);

        $publisher->notifyUser(
            $this->post->user->telegram_id,
            "✅ Пост опубликован в канал: {$this->post->tour->hotel_name}"
        );
    }
}
