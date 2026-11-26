<?php

namespace App\Models;

use App\Support\JanunPlan;
use App\Support\Url;
use Illuminate\Database\Eloquent\Model;

/** D301: one "জেনে রাখুন" reference page. */
class JanunPage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['body' => 'array', 'check' => 'array', 'publish_at' => 'datetime', 'checked_at' => 'datetime'];
    }

    public function scopeLive($q)
    {
        return $q->where('status', 'published')->where('publish_at', '<=', now());
    }

    public function url(): string
    {
        return Url::to('/janun/'.$this->section.'/'.$this->slug);
    }

    /** The later of going live and the last check: what Google is told as the page's date (SEO review). */
    public function modifiedAt(): ?\Carbon\CarbonInterface
    {
        return collect([$this->publish_at, $this->checked_at])->filter()->max();
    }

    public function sectionName(): string
    {
        return JanunPlan::SECTIONS[$this->section][0] ?? $this->section;
    }
}
