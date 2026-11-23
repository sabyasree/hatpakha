<?php

namespace App\Models;

use App\Support\Url;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** D108: a serial story (ধারাবাহিক). Its episodes are ordinary posts with serial_id and episode_no. */
class Serial extends Model
{
    protected $guarded = ['id'];

    public const DAYS = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];

    protected function casts(): array
    {
        return ['bible' => 'array', 'started_at' => 'datetime'];
    }

    /** The writer whose serial it is (null: the desk's own, D193). */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Post::class)->orderBy('episode_no');
    }

    public function liveEpisodes()
    {
        return $this->episodes()->published();
    }

    public function latestEpisode(): ?Post
    {
        return $this->episodes()->published()->reorder()->latest('published_at')->first();
    }

    public function url(): string
    {
        return Url::to('/dharabahik/'.$this->slug);
    }

    public function dayName(): string
    {
        return self::DAYS[$this->day] ?? 'শনিবার';
    }

    public function isPublic(): bool
    {
        return in_array($this->status, ['active', 'paused', 'finished'], true) && filled($this->slug);
    }

    public function plannedEpisodes(): int
    {
        return max(1, count((array) ($this->bible['episodes'] ?? [])));
    }

    /**
     * D269 (owner: "episodes can only publish on Saturday"): the serial's day (Saturday) at 9am India time, the next one
     * an hour or more ahead and at least six days after the last episode out or set; an episode's own date is ignored.
     */
    public function nextEpisodeAt(?int $exceptPostId = null): \Illuminate\Support\Carbon
    {
        $tz = 'Asia/Kolkata';
        $from = now($tz)->addHour();
        $before = $this->episodes()->whereIn('status', ['published', 'scheduled'])->when($exceptPostId, fn ($q) => $q->whereKeyNot($exceptPostId))->max('published_at');
        if ($before) {
            $from = $from->max(\Illuminate\Support\Carbon::parse($before)->timezone($tz)->addDays(6)->startOfDay());
        }
        $day = $from->copy()->setTime(9, 0);
        while ($day->dayOfWeek !== (int) ($this->day ?? 6) || $day->lt($from)) {
            $day->addDay()->setTime(9, 0);
        }

        return $day->utc();
    }

    /** Whether a time falls on the serial's day (Saturday), India time. */
    public function isEpisodeDay(\DateTimeInterface $at): bool
    {
        return \Illuminate\Support\Carbon::instance($at)->timezone('Asia/Kolkata')->dayOfWeek === (int) ($this->day ?? 6);
    }

    public function nextEpisodeNo(): int
    {
        return (int) $this->episodes()->max('episode_no') + 1;
    }

    /** Serials readers can see, newest episode first. */
    public static function live()
    {
        return self::whereIn('status', ['active', 'paused', 'finished'])->whereNotNull('slug')
            ->whereHas('episodes', fn ($q) => $q->published())
            ->withMax(['episodes as last_episode_at' => fn ($q) => $q->published()], 'published_at')
            ->orderByDesc('last_episode_at');
    }

    public static function uniqueSlug(string $title): string
    {
        $base = \App\Support\Transliterate::slug($title) ?: 'dharabahik';
        $slug = $base;
        for ($i = 2; self::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** The serial goes live (its address is fixed now); episodes are still published one by one by the owner. */
    public function activate(): void
    {
        $this->forceFill(['status' => 'active', 'slug' => $this->slug ?: self::uniqueSlug($this->title), 'started_at' => $this->started_at ?? now()])->save();
        EditorTask::open('serial')->where('subject_type', $this->getMorphClass())->where('subject_id', $this->id)->get()->each->close();
        AuditLog::record('serial.started', null, ['serial' => $this->id]);
    }

    public function isFollowedBy(?User $user): bool
    {
        return $user && \Illuminate\Support\Facades\DB::table('serial_follows')->where(['serial_id' => $this->id, 'user_id' => $user->id])->exists();
    }
}
