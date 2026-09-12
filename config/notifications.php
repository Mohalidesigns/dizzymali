<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    | Each channel can be switched off globally here. A channel that is off is
    | skipped at send time and recorded in the log, so a message is never
    | silently lost — you can always tell the difference between "not sent" and
    | "sent and failed".
    */

    'channels' => [
        'mail' => [
            'enabled' => env('NOTIFY_MAIL_ENABLED', true),
        ],

        'database' => [
            'enabled' => env('NOTIFY_DATABASE_ENABLED', true),
        ],

        /*
        | WhatsApp is built and wired but held. Set WHATSAPP_ENABLED=true and
        | fill in the credentials to switch it on; nothing else changes.
        |
        | Template approval from Meta takes days, so the template names below
        | are configurable — put whatever Meta approves into the .env rather
        | than editing code.
        */
        'whatsapp' => [
            'enabled' => env('WHATSAPP_ENABLED', false),
            'driver' => env('WHATSAPP_DRIVER', 'cloud_api'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp drivers
    |--------------------------------------------------------------------------
    | 'cloud_api' is Meta's own WhatsApp Business Cloud API. 'log' writes the
    | message to the log instead of sending, which is what runs today. Adding a
    | third provider means implementing one interface and naming it here — no
    | calling code changes.
    */

    'whatsapp' => [
        'default' => env('WHATSAPP_DRIVER', 'log'),

        'drivers' => [
            'cloud_api' => [
                'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
                'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
                'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
                'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
                'default_locale' => env('WHATSAPP_TEMPLATE_LOCALE', 'en'),
            ],

            'log' => [
                'channel' => env('WHATSAPP_LOG_CHANNEL', 'stack'),
            ],
        ],

        /*
        | Meta-approved template names, per notification. Until approval comes
        | through these are placeholders; the codebase refers to the key, never
        | the template name, so approval is a .env change.
        */
        'templates' => [
            'order_submitted' => env('WHATSAPP_TPL_ORDER_SUBMITTED', 'dizzymali_order_submitted'),
            'payment_received' => env('WHATSAPP_TPL_PAYMENT_RECEIVED', 'dizzymali_payment_received'),
            'stage_changed' => env('WHATSAPP_TPL_STAGE_CHANGED', 'dizzymali_stage_changed'),
            'progress_photo' => env('WHATSAPP_TPL_PROGRESS_PHOTO', 'dizzymali_progress_photo'),
            'order_shipped' => env('WHATSAPP_TPL_ORDER_SHIPPED', 'dizzymali_order_shipped'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Which channels each notification uses
    |--------------------------------------------------------------------------
    | Matches §11 of the implementation plan. Admins can override these per
    | notification from the admin panel; this is the default.
    */

    'matrix' => [
        'registered' => ['mail'],
        'order_submitted' => ['mail', 'whatsapp', 'database'],
        'payment_received' => ['mail', 'whatsapp', 'database'],
        'stage_changed' => ['whatsapp', 'database'],
        'progress_photo' => ['mail', 'whatsapp', 'database'],
        'order_shipped' => ['mail', 'whatsapp', 'database'],
        'order_delivered' => ['mail', 'database'],
        'draft_abandoned' => ['mail'],
    ],
];
