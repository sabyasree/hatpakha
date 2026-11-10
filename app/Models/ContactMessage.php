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
        // D94: a writer asks the editor to change a piece that is submitted or published (writers can't edit it).
        'change' => [
            'piece' => 'লেখায় বদল চাওয়া',
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

    /** A short notice to the admins: who and what about, never the visitor's text or links. */
    public function notifyAdmins(): void
    {
        $admins = User::where('role', \App\Enums\Role::Admin->value)->where('mail_opt_out', false)->get();
        $msg = $this;
        dispatch(function () use ($admins, $msg) {
            $where = ['advertise' => 'বিজ্ঞাপন ফর্মে', 'change' => 'লেখকের কাছ থেকে', 'contact' => 'যোগাযোগ ফর্মে'][$msg->kind] ?? 'ওয়েবসাইটে';
            foreach ($admins as $a) {
                $a->notify(new \App\Notifications\WriterNote('হাতপাখা: নতুন বার্তা ('.$msg->topicLabel().')',
                    $where.' নতুন বার্তা এসেছে। ডেস্কে "বার্তা" খুলে পড়ুন ও উত্তর দিন।',
                    \App\Filament\Resources\ContactMessages\ContactMessageResource::getUrl('index', panel: 'desk'), 'বার্তা দেখুন'));
            }
        })->afterResponse();
    }

    public function post(): ?Post
    {
        return ($id = $this->interests['post_id'] ?? null) ? Post::find($id) : null;
    }

    public function topicLabel(): string
    {
        return self::TOPICS[$this->kind][$this->topic] ?? $this->topic;
    }
}
