<?php

use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;

Route::post('/telegram/webhook', function (Nutgram $bot) {
    $secret = config('nutgram.secret');
    if ($secret && request()->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
        abort(403);
    }

    $bot->setRunningMode(Webhook::class);
    $bot->run();
});
