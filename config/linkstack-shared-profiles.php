<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'auto_approve' => env('LINKSTACK_SHARED_PROFILES_AUTO_APPROVE', false),
    'auth_date_ttl' => 300, // seconds before initData is considered stale
    'default_button_id' => env('TELEGRAM_DEFAULT_BUTTON_ID'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'auth_button_label' => env('TELEGRAM_AUTH_BUTTON_LABEL', 'Log in to LinkStack'),
];
