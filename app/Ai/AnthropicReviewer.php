<?php

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Post;

/** Claude via the official SDK (D34). Structured output, cached rubric, server-side refusal fallback. */
final class AnthropicReviewer implements Reviewer
{
    public function __construct(private ?Client $client = null) {}

    public function provider(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return (string) config('ai.anthropic.review_model');
    }

    public function review(Post $post, array $sections): ReviewOutcome
    {
        $client = $this->client ?? new Client(apiKey: (string) config('ai.anthropic.key'));

        try {
            $message = $client->beta->messages->create(
                model: $this->model(),
                maxTokens: 16000,
                // The rubric is the same on every call: cache it (cheaper, faster). Per-piece text goes in messages.
                system: [['type' => 'text', 'text' => trim(Knowledge::BRIEF)], ['type' => 'text', 'text' => Prompt::system(), 'cacheControl' => ['type' => 'ephemeral']]],   // D62: shared knowledge first
                messages: [['role' => 'user', 'content' => Prompt::user($post)]],
                outputConfig: [
                    'effort' => (string) config('ai.anthropic.effort'),
                    'format' => ['type' => 'json_schema', 'schema' => ReviewSchema::schema($sections)],
                ],
                // If a safety classifier declines, the API retries on a suitable model within the same call.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (APIStatusException $e) {
            return new ReviewOutcome(null, error: 'API '.($e->type?->value ?? 'error').': '.mb_substr($e->getMessage(), 0, 200));
        } catch (APIConnectionException $e) {
            return new ReviewOutcome(null, error: 'Connection: '.mb_substr($e->getMessage(), 0, 200));
        }

        $u = $message->usage;
        $usage = ['in' => $u->inputTokens, 'out' => $u->outputTokens, 'cache_read' => (int) $u->cacheReadInputTokens, 'cache_write' => (int) $u->cacheCreationInputTokens];

        if ($message->stopReason === 'refusal') {
            return new ReviewOutcome(null, $usage, 'refusal: '.($message->stopDetails?->category ?? 'unspecified').' (editor reviews manually)');
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);

                return is_array($data) ? new ReviewOutcome(ReviewSchema::normalise($data), $usage) : new ReviewOutcome(null, $usage, 'invalid JSON');
            }
        }

        return new ReviewOutcome(null, $usage, 'no text block (stop: '.($message->stopReason ?? '?').')');
    }
}
