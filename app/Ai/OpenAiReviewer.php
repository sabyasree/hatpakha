<?php

namespace App\Ai;

use App\Models\Post;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI via Laravel's HTTP client (no official OpenAI PHP SDK exists; D34). Responses API with a strict
 * JSON schema, so both providers return the identical report shape for the blind test.
 */
final class OpenAiReviewer implements Reviewer
{
    public function provider(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('ai.openai.review_model');
    }

    public function review(Post $post, array $sections): ReviewOutcome
    {
        if ($this->model() === '') {
            return new ReviewOutcome(null, error: 'OPENAI_REVIEW_MODEL is not set');
        }
        $res = Http::withToken((string) config('ai.openai.key'))->timeout(180)->retry(2, 2000, throw: false)
            ->post(rtrim((string) config('ai.openai.base_url'), '/').'/responses', [
                'model' => $this->model(),
                'instructions' => Prompt::system(),
                'input' => Prompt::user($post),
                'text' => ['format' => ['type' => 'json_schema', 'name' => 'review', 'strict' => true, 'schema' => ReviewSchema::schema($sections)]],
            ]);

        if (! $res->successful()) {
            return new ReviewOutcome(null, error: 'API '.$res->status().': '.mb_substr((string) ($res->json('error.message') ?? $res->body()), 0, 200));
        }
        $u = (array) $res->json('usage', []);
        $cached = (int) data_get($u, 'input_tokens_details.cached_tokens', 0);
        $usage = ['in' => max(0, (int) ($u['input_tokens'] ?? 0) - $cached), 'out' => (int) ($u['output_tokens'] ?? 0), 'cache_read' => $cached, 'cache_write' => 0];

        $text = null;
        foreach ((array) $res->json('output', []) as $item) {
            foreach ((array) ($item['content'] ?? []) as $c) {
                if (($c['type'] ?? '') === 'refusal') {
                    return new ReviewOutcome(null, $usage, 'refusal (editor reviews manually)');
                }
                if (($c['type'] ?? '') === 'output_text') {
                    $text = $c['text'];
                }
            }
        }
        $data = $text !== null ? json_decode($text, true) : null;

        return is_array($data) ? new ReviewOutcome(ReviewSchema::normalise($data), $usage) : new ReviewOutcome(null, $usage, 'invalid or missing JSON');
    }
}
