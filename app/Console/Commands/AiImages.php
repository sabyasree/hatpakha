<?php

namespace App\Console\Commands;

use App\Ai\Illustrator;
use App\Models\Post;
use Illuminate\Console\Command;

/** Cover illustrations for pieces without a picture (D43). Proposals only; the owner approves. */
class AiImages extends Command
{
    protected $signature = 'hatpakha:ai-images {--limit=500} {--retry : Also retry pieces that failed before} {--dry}';

    protected $description = 'Propose AI cover illustrations for pieces without a picture';

    public function handle(): int
    {
        $q = Illustrator::candidates()
            ->when(! $this->option('retry'), fn ($q) => $q->whereNull('proposed_image_error'))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderByDesc('published_at');
        $posts = $q->limit((int) $this->option('limit'))->get();
        $this->info(sprintf('Images: %d pieces without a picture. Estimated cost: $%.2f (+ a little for the scene descriptions).',
            $posts->count(), $posts->count() * (float) config('ai.openai.image_price_usd')));
        if ($this->option('dry') || $posts->isEmpty()) {
            return self::SUCCESS;
        }
        if ($why = Illustrator::blocked()) {
            $this->warn("Images not made: {$why}.");

            return self::SUCCESS;
        }
        $bar = $this->output->createProgressBar($posts->count());
        $made = 0;
        foreach ($posts as $post) {
            $why = Illustrator::make($post);
            $why === null ? $made++ : null;
            if ($why && str_contains($why, 'paused')) {
                $this->newLine();
                $this->warn('AI is paused by an admin: stopping.');
                break;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Images: {$made} proposed. They appear after the owner approves them in /desk.");

        return self::SUCCESS;
    }
}
