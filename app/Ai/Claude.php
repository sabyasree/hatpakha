<?php

namespace App\Ai;

use Anthropic\Client;
use App\Models\AiUsage;

/**
 * One structured call to Claude for the AI editor's smaller jobs (image scenes, comment screening,
 * issue planning, editorial drafts). Respects the on/off switch and the owner's pause button,
 * logs every call's cost, and never throws: callers get [data|null, error|null].
 */
final class Claude
{
    /** Tests replace the API with a closure: fn (string $purpose, string $user, array $schema) => array|null */
    public static ?\Closure $fake = null;

    public static function blocked(): ?string
    {
        if (! config('ai.enabled')) {
            return 'AI is switched off';
        }
        if (AiSwitch::paused()) {
            return 'AI is paused by an admin';
        }

        return blank(config('ai.anthropic.key')) ? 'No API key for anthropic' : null;
    }

    /** @return array{0: ?array, 1: ?string} */
    public static function json(string $purpose, string $system, string $user, array $schema, ?string $model = null, int $maxTokens = 8000, ?int $postId = null): array
    {
        if ($why = self::blocked()) {
            return [null, $why];
        }
        if (self::$fake) {
            return [(self::$fake)($purpose, $user, $schema), null];
        }
        $model ??= (string) config('ai.anthropic.review_model');
        try {
            $message = (new Client(apiKey: (string) config('ai.anthropic.key')))->beta->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                messages: [['role' => 'user', 'content' => $user]],
                outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $schema]],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (\Throwable $e) {
            return [null, mb_substr($e->getMessage(), 0, 200)];
        }
        $u = $message->usage;
        self::log('anthropic', $model, $purpose, $postId, ['in' => $u->inputTokens, 'out' => $u->outputTokens, 'cache_read' => (int) $u->cacheReadInputTokens, 'cache_write' => (int) $u->cacheCreationInputTokens]);
        if ($message->stopReason === 'refusal') {
            return [null, 'refusal'];
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);

                return is_array($data) ? [$data, null] : [null, 'invalid JSON'];
            }
        }

        return [null, 'no text'];
    }

    /** @param array{in:int,out:int,cache_read:int,cache_write:int} $u */
    public static function log(string $provider, string $model, string $purpose, ?int $postId, array $u, ?float $costUsd = null): void
    {
        AiUsage::create([
            'provider' => $provider, 'model' => $model, 'purpose' => $purpose, 'post_id' => $postId,
            'input_tokens' => $u['in'], 'output_tokens' => $u['out'], 'cache_read_tokens' => $u['cache_read'], 'cache_write_tokens' => $u['cache_write'],
            'cost_usd' => $costUsd ?? ReviewService::cost($model, $u),
        ]);
    }
}
