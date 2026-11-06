<?php

namespace App\Ai;

use Illuminate\Support\Facades\Http;

/**
 * Checks that each provider's key and configured model work, using the providers' free
 * "get model" endpoints (no tokens, no cost). Never returns or logs the key itself.
 */
class KeyCheck
{
    /** @return array<string, array{ok: bool, message: string}> */
    public static function run(): array
    {
        return ['anthropic' => self::anthropic(), 'openai' => self::openai()];
    }

    private static function anthropic(): array
    {
        $key = (string) config('ai.anthropic.key');
        $model = (string) config('ai.anthropic.review_model');
        if ($key === '') {
            return ['ok' => false, 'message' => 'চাবি দেওয়া নেই'];
        }
        $r = rescue(fn () => Http::timeout(15)->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->get('https://api.anthropic.com/v1/models/'.rawurlencode($model)), null, false);

        return self::verdict($r, $model);
    }

    private static function openai(): array
    {
        $key = (string) config('ai.openai.key');
        $model = (string) config('ai.openai.review_model');
        if ($key === '') {
            return ['ok' => false, 'message' => 'চাবি দেওয়া নেই'];
        }
        if ($model === '') {
            return ['ok' => false, 'message' => 'OPENAI_REVIEW_MODEL দেওয়া নেই'];
        }
        $r = rescue(fn () => Http::timeout(15)->withToken($key)
            ->get(rtrim((string) config('ai.openai.base_url'), '/').'/models/'.rawurlencode($model)), null, false);

        return self::verdict($r, $model);
    }

    private static function verdict($response, string $model): array
    {
        return match (true) {
            $response === null => ['ok' => false, 'message' => 'সংযোগ হলো না (নেটওয়ার্ক)'],
            $response->successful() => ['ok' => true, 'message' => "ঠিক আছে ({$model})"],
            in_array($response->status(), [401, 403], true) => ['ok' => false, 'message' => 'চাবিটি ঠিক নয় বা বাতিল'],
            $response->status() === 404 => ['ok' => false, 'message' => "মডেলের নাম ভুল: {$model}"],
            default => ['ok' => false, 'message' => 'উত্তর এল না (কোড '.$response->status().')'],
        };
    }
}
