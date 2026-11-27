<?php

namespace App\Console\Commands;

use App\Http\Controllers\LearnController;
use Illuminate\Console\Command;

/** D321: the list of বাংলা শিখি PDFs (file => print page) for the deploy job, which draws them in a browser (scripts/learn-pdfs.mjs). */
class LearnPdfs extends Command
{
    protected $signature = 'hatpakha:learn-pdfs {--list : print the list as JSON}';

    protected $description = 'বাংলা শিখি: list the worksheets, teacher\'s sets and charts the deploy job saves as PDF';

    public function handle(): int
    {
        $this->line(json_encode(LearnController::pdfs(), JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
