<?php

namespace App\Filament\Resources\PieceStats\Pages;

use App\Filament\Resources\PieceStats\PieceStatResource;
use App\Support\Bn;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListPieceStats extends ListRecords
{
    protected static string $resource = PieceStatResource::class;

    /** The whole site in one line: the last 30 days against the 30 before. */
    public function getSubheading(): ?string
    {
        $sum = fn (int $from, int $to) => (int) DB::table('post_views')->whereBetween('day', [now()->subDays($from)->toDateString(), now()->subDays($to)->toDateString()])->sum('views');
        $now = $sum(29, 0);
        $before = $sum(59, 30);

        return __('গত ৩০ দিনে সব লেখা মিলিয়ে :now বার খোলা হয়েছে (তার আগের ৩০ দিনে :before বার)। যে কোনো কলামের নামে টিপলে সেই অনুযায়ী সাজানো হবে।',
            ['now' => Bn::num((string) $now), 'before' => Bn::num((string) $before)]);
    }
}
