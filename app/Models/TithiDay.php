<?php

namespace App\Models;

use App\Support\Tithi;
use Illuminate\Database\Eloquent\Model;

/** D310: one day's tithi in Kolkata (shown only when the published page and our calculation agree). */
class TithiDay extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['day' => 'date', 'turns' => 'array'];
    }

    /** "শুক্লপক্ষ পঞ্চমী, রাত ৩:১৮ পর্যন্ত (পরদিন), তারপর ষষ্ঠী" (India time) */
    public function text(): string
    {
        $out = Tithi::name($this->tithi);
        $changes = (array) $this->turns;
        if ($changes === []) {
            return $out.' (সারাদিন)';
        }
        foreach ($changes as $i => [$at, $next]) {
            $t = \Carbon\CarbonImmutable::parse($at)->setTimezone('Asia/Kolkata');
            $nextDay = $t->toDateString() !== $this->day->toDateString();
            $out .= ($i ? ', তারপর '.Tithi::name((int) $changes[$i - 1][1]) : '').', '.\App\Support\FestivalPlan::part($t->hour).' '.\App\Support\Bn::num($t->format('g:i')).' পর্যন্ত'.($nextDay ? ' (পরদিন)' : '');
        }

        return $out.', তারপর '.Tithi::name((int) end($changes)[1]);
    }
}
