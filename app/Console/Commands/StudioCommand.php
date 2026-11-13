<?php

namespace App\Console\Commands;

use App\Support\Studio;
use Illuminate\Console\Command;

/** D182: the two ends of the marketing studio's drawing job (see App\Support\Studio). Run over SSH by GitHub Actions. */
class StudioCommand extends Command
{
    protected $signature = 'hatpakha:studio {what : list (what is waiting, as JSON) or take (move the uploaded files in)}';

    protected $description = 'Marketing studio: list what needs drawing, or take in what was drawn';

    public function handle(): int
    {
        if ($this->argument('what') === 'list') {
            $this->line(json_encode(Studio::pending(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        $this->info(Studio::take().' taken in');

        return self::SUCCESS;
    }
}
