<?php

namespace App\Telegram\Handlers;

use App\Jobs\ProcessTourBatchJob;
use App\Models\TourBatch;
use App\Models\User;
use SergiX44\Nutgram\Nutgram;

class LinkHandler
{
    public function __invoke(Nutgram $bot): void
    {
        /** @var User $user */
        $user = $bot->get('db_user');

        $url = trim($bot->message()->text ?? '');

        if (!preg_match('#https?://qui-quo\.com/.+#', $url)) {
            $bot->sendMessage('Отправьте ссылку с qui-quo.com');
            return;
        }

        $batch = TourBatch::create([
            'user_id'    => $user->id,
            'source_url' => $url,
        ]);

        $bot->sendMessage('Парсю подборку, подождите...');

        ProcessTourBatchJob::dispatch($batch, $bot->chatId());
    }
}
