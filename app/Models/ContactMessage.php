<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message from the contact or advertising form (D89). Plain text; always printed escaped. */
class ContactMessage extends Model
{
    protected $guarded = ['id'];

    public const TOPICS = [
        'contact' => [
            'question' => 'প্রশ্ন বা মতামত',
            'correction' => 'প্রকাশিত লেখায় সংশোধন',
            'copyright' => 'স্বত্ব বা নকল নিয়ে অভিযোগ',
            'website' => 'ওয়েবসাইটে সমস্যা',
            'other' => 'অন্য কিছু',
        ],
        'advertise' => [
            'sponsor_issue' => 'উৎসব বা মাসিক সংখ্যার স্পনসর',
            'sponsor_contest' => 'লেখা প্রতিযোগিতার স্পনসর',
            'book_event' => 'বইপ্রকাশ বা অনুষ্ঠানের খবর',
            'other' => 'অন্য ধরনের সহযোগিতা',
        ],
    ];

    protected function casts(): array
    {
        return ['interests' => 'array', 'handled_at' => 'datetime'];
    }

    public function handler(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function topicLabel(): string
    {
        return self::TOPICS[$this->kind][$this->topic] ?? $this->topic;
    }
}
