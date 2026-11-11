<?php

namespace App\Console\Commands;

use App\Support\WebPush;
use Illuminate\Console\Command;

/** D127: a key pair for phone notifications. Prints the lines for ENV_FILE; stores nothing. */
class MakeVapidKeys extends Command
{
    protected $signature = 'hatpakha:vapid';

    protected $description = 'Make the key pair for phone notifications (prints lines for ENV_FILE, stores nothing)';

    public function handle(): int
    {
        [$public, $private] = WebPush::newKeys();
        $this->line('Add these two lines to the ENV_FILE secret in GitHub (Settings → Environments → production), then redeploy.');
        $this->line('Keep the private key secret: don\'t paste it into chat or e-mail. Changing the keys later turns notifications off for everyone who had them on.');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$public);
        $this->line('VAPID_PRIVATE_KEY='.$private);

        return self::SUCCESS;
    }
}
