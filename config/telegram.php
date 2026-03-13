<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'channel_id' => env('TELEGRAM_CHANNEL_ID'),
    'admin_id' => (int) env('TELEGRAM_ADMIN_ID'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
];
