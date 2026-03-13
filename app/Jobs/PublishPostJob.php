<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\TelegramPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Post $post) {}

    public function handle(TelegramPublisher $publisher): void
    {
        Log::info("PublishPostJob: starting for post {$this->post->id}");

        $this->post->refresh();

        Log::info("PublishPostJob: post {$this->post->id} status={$this->post->status}");

        if ($this->post->status !== 'scheduled') {
            Log::warning("PublishPostJob: post {$this->post->id} status is not 'scheduled', skipping");
            return;
        }

        Log::info("PublishPostJob: publishing post {$this->post->id} to channel " . config('telegram.channel_id'));

        $messageId = $publisher->publishToChannel($this->post->generated_text);

        Log::info("PublishPostJob: post {$this->post->id} published, telegram_message_id={$messageId}");

        $this->post->update([
            'status' => 'published',
            'published_at' => now(),
            'telegram_message_id' => $messageId,
        ]);

        $publisher->notifyUser(
            $this->post->user->telegram_id,
            "✅ Пост опубликован в канал: {$this->post->tour->hotel_name}"
        );

        Log::info("PublishPostJob: done for post {$this->post->id}");
    }
}
