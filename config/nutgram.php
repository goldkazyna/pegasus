<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    |
    | Your Telegram Bot API token, obtained from @BotFather.
    |
    */

    'token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret Token
    |--------------------------------------------------------------------------
    |
    | A secret token set via setWebhook to verify that requests come from
    | Telegram. Checked against the X-Telegram-Bot-Api-Secret-Token header.
    |
    */

    'secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Bot API URL
    |--------------------------------------------------------------------------
    |
    | The Telegram Bot API URL. Change this if you are using a local API server.
    |
    */

    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | The HTTP client timeout in seconds.
    |
    */

    'timeout' => env('TELEGRAM_CLIENT_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Polling
    |--------------------------------------------------------------------------
    |
    | Settings for the polling mode.
    |
    */

    'polling' => [
        'timeout' => 10,
        'limit' => 100,
        'allowed_updates' => null,
    ],

];
