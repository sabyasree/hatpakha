<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** D113: a human reader's feedback on an AI-written piece; may become a standing rule for future AI writing. */
class WritingFeedback extends Model
{
    protected $table = 'writing_feedback';

    protected $guarded = ['id'];

    public const SCOPES = ['section' => 'এই বিভাগের লেখায়', 'serial' => 'এই ধারাবাহিকের পরের পর্বে', 'all' => 'সব এআই লেখায়'];

    protected function casts(): array
    {
        return ['apply_now' => 'boolean', 'apply_future' => 'boolean', 'active' => 'boolean'];
    }

    public function post(): BelongsTo { return $this->belongsTo(Post::class); }

    public function section(): BelongsTo { return $this->belongsTo(Section::class); }

    public function serial(): BelongsTo { return $this->belongsTo(Serial::class); }

    public function scopeLabel(): string
    {
        return match ($this->scope) {
            'section' => ($this->section?->name_bn ?? 'বিভাগ').' বিভাগে',
            'serial' => '"'.($this->serial?->title ?? 'ধারাবাহিক').'" ধারাবাহিকে',
            default => 'সব এআই লেখায়',
        };
    }
}
