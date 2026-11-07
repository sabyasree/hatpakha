<?php

/*
 * AI review layer (PLAN.md §4, D11): provider-neutral; AI advises editors, never decides (CLAUDE.md §2).
 * Keys live only in the GitHub Environment ENV_FILE (never in git).
 */
return [
    // Off unless explicitly enabled; nothing is sent anywhere without a key and this flag.
    'enabled' => (bool) env('AI_ENABLED', false),
    'provider' => env('AI_PROVIDER', 'anthropic'),   // decided by the blind test (3e)

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        // Owner-approved plan: mid tier for submission reviews, small tier for tags/SEO.
        'review_model' => env('ANTHROPIC_REVIEW_MODEL', 'claude-sonnet-5-5'),
        'tags_model' => env('ANTHROPIC_TAGS_MODEL', 'claude-haiku-5-5'),
        'effort' => env('ANTHROPIC_REVIEW_EFFORT', 'medium'),
    ],
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        // No default on purpose: set the exact model you want compared (see docs/AI.md).
        'review_model' => env('OPENAI_REVIEW_MODEL'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        // Cover illustrations (D43). No default model on purpose: copy the exact name from OpenAI's models page.
        'image_model' => env('OPENAI_IMAGE_MODEL'),
        'image_size' => env('OPENAI_IMAGE_SIZE', '1536x1024'),
        'image_quality' => env('OPENAI_IMAGE_QUALITY', 'medium'),
        'image_price_usd' => (float) env('OPENAI_IMAGE_PRICE', 0),   // per image, for the spend log
        // D101: the narrator's AI voice (text to speech) for ছোটদের হাতপাখা
        'tts_model' => env('OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
        'tts_voice' => env('OPENAI_TTS_VOICE', 'coral'),
        'tts_price_per_1k_chars' => (float) env('OPENAI_TTS_PRICE_1K', 0.015),   // estimate for the spend log
    ],

    'min_words' => (int) env('AI_MIN_WORDS', 20),

    // USD per 1M tokens, for the spend log. Update when prices change (docs/AI.md).
    'prices' => [
        'claude-sonnet-5-5' => ['in' => 2.00, 'out' => 10.00, 'cache_read' => 0.20, 'cache_write' => 2.50],
        'claude-haiku-5-5' => ['in' => 0.10, 'out' => 0.50, 'cache_read' => 0.01, 'cache_write' => 0.125],
        'claude-opus-5-5' => ['in' => 4.00, 'out' => 20.00, 'cache_read' => 0.20, 'cache_write' => 5.00],
        // OpenAI prices: fill in from OpenAI's pricing page for the chosen OPENAI_REVIEW_MODEL.
        'openai' => ['in' => (float) env('OPENAI_PRICE_IN', 0), 'out' => (float) env('OPENAI_PRICE_OUT', 0), 'cache_read' => (float) env('OPENAI_PRICE_CACHED', 0), 'cache_write' => 0],
    ],
];
