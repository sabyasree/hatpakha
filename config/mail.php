<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        // D117: newsletters and other bulk mail (Amazon SES over SMTP); account mail keeps the default mailer.
        'bulk' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_BULK_SCHEME', 'smtp'),
            'host' => env('MAIL_BULK_HOST'),
            'port' => env('MAIL_BULK_PORT', 587),
            'username' => env('MAIL_BULK_USERNAME'),
            'password' => env('MAIL_BULK_PASSWORD'),
            'timeout' => 30,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        // D129: Brevo (free plan: 300 a day) as a second road for newsletters; chosen in /desk → চিঠি → "পাঠানোর পথ".
        'brevo' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_BREVO_SCHEME', 'smtp'),
            'host' => env('MAIL_BREVO_HOST'),
            'port' => env('MAIL_BREVO_PORT', 587),
            'username' => env('MAIL_BREVO_USERNAME'),
            'password' => env('MAIL_BREVO_PASSWORD'),
            'timeout' => 30,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],


    // Newsletters (D58): letters sent per minute by the scheduler. Shared hosting allows only so many per hour.
    'batch' => (int) env('MAIL_BATCH', 30),
    // D117: at most this many bulk e-mails in any 24 hours (0 = no limit). SES starts at 200 a day until Amazon
    // lifts the account out of its "sandbox"; set it to the daily quota the SES console shows.
    'daily_limit' => (int) env('MAIL_DAILY_LIMIT', 0),
    // D129: Brevo's own limit (free plan 300 a day; a little under it leaves room for test letters).
    'brevo_daily_limit' => (int) env('MAIL_BREVO_DAILY_LIMIT', 290),
    // D190: the Brevo plan counts by the month (Starter: 5,000). 0 = no monthly limit kept by the site.
    'brevo_monthly_limit' => (int) env('MAIL_BREVO_MONTHLY_LIMIT', 0),
    // Off hatpakha.com (staging), mail goes only to desk staff and bulk mail is off (App\Support\MailGuard).
    // "allow" switches the guard off; only the test suite uses it.
    'off_live' => env('MAIL_OFF_LIVE', 'block'),
    // The SNS topic SES reports bounces and complaints to; webhooks from any other topic are refused.
    'ses_topic_arn' => env('SES_SNS_TOPIC_ARN'),
    // D164: the secret part of the Brevo webhook address (/webhooks/brevo/<token>); empty = derived from APP_KEY.
    'brevo_webhook_token' => env('BREVO_WEBHOOK_TOKEN'),

    // D104: apology letters to old writers, per hour (old addresses: go slowly so bounces don't hurt the site's mail).
    'relaunch_per_hour' => (int) env('MAIL_RELAUNCH_PER_HOUR', 20),

];
