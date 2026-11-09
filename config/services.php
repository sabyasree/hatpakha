<?php

return [

    // Google AdSense publisher ID (ca-pub-…). Ads render only when set. docs/SEO.md §5.
    // Free captcha for guest comments; off until both keys are set (App\Support\Turnstile).
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET'),
        // Our own hostnames; a token from anywhere else is refused (D60). Default: the APP_URL host.
        'hostnames' => env('TURNSTILE_HOSTNAMES', parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: ''),
    ],

    // D116: the magazine's own social pages (footer link + Organization sameAs) and Meta's domain verification code.
    'social' => [
        'facebook' => env('FACEBOOK_PAGE_URL', 'https://www.facebook.com/Hatpakhaonline'),
        'instagram' => env('INSTAGRAM_URL', 'https://www.instagram.com/hatpakha/'),
        'facebook_domain_verification' => env('FACEBOOK_DOMAIN_VERIFICATION'),
    ],

    // D111: one slot id per ad spot, created in AdSense; an empty one simply doesn't show.
    'adsense' => [
        'client' => env('ADSENSE_CLIENT', 'ca-pub-4627594058272769'),
        'force' => (bool) env('ADSENSE_FORCE', false),   // show ads off the live site (testing only)
        'slots' => [
            'home' => env('ADSENSE_SLOT_HOME'),
            'home_2' => env('ADSENSE_SLOT_HOME_2'),
            'article' => env('ADSENSE_SLOT_ARTICLE'),
            'article_2' => env('ADSENSE_SLOT_ARTICLE_2'),
            'sidebar' => env('ADSENSE_SLOT_SIDEBAR'),
            'list' => env('ADSENSE_SLOT_LIST'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
