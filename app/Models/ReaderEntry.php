<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** D175: one reader's answer to the week's theme. Plain text; shown only once picked and the owner publishes. */
class ReaderEntry extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['new' => 'নতুন', 'ok' => 'এআই দেখেছে: ঠিক আছে', 'flagged' => 'এআই-এর আপত্তি', 'picked' => 'বাছাই', 'rejected' => 'বাদ'];

    protected function casts(): array
    {
        return ['anonymous' => 'boolean', 'ai_score' => 'integer'];
    }

    public function prompt(): BelongsTo { return $this->belongsTo(ReaderPrompt::class, 'reader_prompt_id'); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function shownName(): string { return $this->anonymous ? 'নাম প্রকাশে অনিচ্ছুক' : $this->name; }
}
