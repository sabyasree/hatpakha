<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** D321: a human recording of a letter, word or sentence of বাংলা শিখি, uploaded at the desk by an editor. */
class LearnAudio extends Model
{
    protected $table = 'learn_audio';

    protected $guarded = ['id'];

    public const DIR = 'media/learn-audio';

    /** @return array<string, string> text => public URL, for the texts asked */
    public static function urls(array $texts): array
    {
        $texts = array_values(array_unique(array_map([\App\Support\LearnCourse::class, 'nfc'], array_filter($texts))));

        return $texts === [] ? [] : self::whereIn('text', $texts)->get()->mapWithKeys(fn ($a) => [$a->text => asset($a->path).'?v='.$a->updated_at?->timestamp])->all();
    }
}
