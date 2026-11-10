<?php

namespace App\Console\Commands;

use App\Support\CoverImage;
use Illuminate\Console\Command;

/** Moves covers saved under storage/ to public/media/ (safe to run on every deploy). */
class MoveCovers extends Command
{
    protected $signature = 'hatpakha:move-covers';

    protected $description = 'Move uploaded/AI covers to public/media (Hostinger does not follow the storage symlink)';

    public function handle(): int
    {
        $this->info('Covers moved: '.CoverImage::moveLegacy());

        return self::SUCCESS;
    }
}
