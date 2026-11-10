<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A letter to readers and/or writers (D58). Plain text fields, escaped in the e-mail. */
class Newsletter extends Model
{
    protected $guarded = ['id'];

    public const AUDIENCES = ['readers' => 'পাঠক (যাঁরা চিঠি চেয়েছেন)', 'writers' => 'লেখক (নিবন্ধিত)', 'both' => 'পাঠক ও লেখক'];

    public const STATUS = ['draft' => 'খসড়া', 'queued' => 'পাঠানোর অপেক্ষায়', 'sending' => 'পাঠানো হচ্ছে', 'sent' => 'পাঠানো হয়েছে', 'cancelled' => 'বাতিল'];

    public const SOURCES = ['owner' => 'আপনি', 'ai_weekly' => 'এআই (সাপ্তাহিক)', 'ai_writers' => 'এআই (লেখকদের চিঠি)', 'ai_topic' => 'এআই (আপনার বিষয়)'];

    protected function casts(): array
    {
        return ['items' => 'array', 'queued_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function deliveries(): HasMany { return $this->hasMany(NewsletterDelivery::class); }

    public function editable(): bool { return $this->status === 'draft'; }
}
