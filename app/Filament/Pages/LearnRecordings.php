<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\LearnAudio;
use App\Support\LearnCourse;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

/**
 * D321 (owner, 2026-11-27: "wait for human recording, give option in desk, so I can upload"): every letter, word and
 * sentence of বাংলা শিখি and the বর্ণমালা, each with an upload. A recording plays in the lesson as soon as it is here.
 * Files: audio only (checked by content, not by name), 3 MB at most, saved under a random name in public/media/learn-audio.
 */
class LearnRecordings extends Page
{
    use WithFileUploads;
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.learn-recordings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    protected static string|\UnitEnum|null $navigationGroup = 'সংখ্যা ও প্রকাশ';

    protected static ?string $navigationLabel = 'বাংলা শিখি: রেকর্ডিং';

    protected static ?string $title = 'বাংলা শিখি: রেকর্ডিং';

    protected static ?string $slug = 'learn-recordings';

    protected static ?int $navigationSort = 7;

    /** Audio types a phone or computer records, and the extension each is saved with. */
    public const TYPES = ['audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac', 'audio/ogg' => 'ogg',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/webm' => 'webm', 'video/webm' => 'webm', 'video/mp4' => 'm4a', 'audio/3gpp' => '3gp'];

    public string $q = '';

    public string $only = 'all';   // all | missing | done

    /** @var array<string, mixed> one upload per row, keyed by the row's key */
    public array $files = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    /** @return array<int, array{0: string, 1: list<array{text: string, hint: string}>}> [group title, rows] */
    public static function groups(): array
    {
        $out = [['বর্ণমালা', collect(\App\Ai\KidsAlphabet::letters())->flatMap(fn ($l) => [['text' => $l[0], 'hint' => 'অক্ষর'], ['text' => $l[1], 'hint' => $l[2]]])->all()]];
        foreach (LearnCourse::units() as $slug => $u) {
            $rows = [];
            foreach ($u['lessons'] as $i => $l) {
                foreach ((array) $l['new'] as $x) {
                    $rows[] = ['text' => $x, 'hint' => __('পাঠ').' '.($i + 1).': '.(LearnCourse::data()['sounds'][$x] ?? __('যুক্তাক্ষর'))];
                }
                foreach ((array) ($l['words'] ?? []) as $w) {
                    $rows[] = ['text' => $w[0], 'hint' => $w[1].' · '.$w[2]];
                }
                foreach ((array) ($l['sentences'] ?? []) as $w) {
                    $rows[] = ['text' => $w[0], 'hint' => $w[2]];
                }
            }
            $out[] = [$u['n'].'. '.$u['title'], collect($rows)->unique('text')->values()->all()];
        }

        return $out;
    }

    public static function key(string $text): string
    {
        return substr(md5(LearnCourse::nfc($text)), 0, 12);
    }

    protected function getViewData(): array
    {
        $groups = self::groups();
        $have = LearnAudio::all()->keyBy('text');
        $q = LearnCourse::nfc(trim($this->q));
        $groups = collect($groups)->map(fn ($g) => [$g[0], collect($g[1])->filter(function ($r) use ($have, $q) {
            $done = $have->has(LearnCourse::nfc($r['text']));

            return ($q === '' || str_contains(LearnCourse::nfc($r['text']), $q) || str_contains(mb_strtolower($r['hint']), mb_strtolower($q)))
                && ($this->only === 'all' || ($this->only === 'done') === $done);
        })->values()->all()])->filter(fn ($g) => $g[1] !== [])->values()->all();
        $total = collect(self::groups())->sum(fn ($g) => count($g[1]));

        return ['groups' => $groups, 'have' => $have, 'count' => $have->count(), 'total' => $total];
    }

    /** A file was chosen in a row: check it and save it at once. */
    public function updatedFiles($value, string $key): void
    {
        $text = collect(self::groups())->flatMap(fn ($g) => $g[1])->first(fn ($r) => self::key($r['text']) === $key)['text'] ?? null;
        if ($text === null) {
            return;
        }
        $this->validate(["files.$key" => 'file|max:3072'], [], ["files.$key" => __('রেকর্ডিং')]);
        $file = $this->files[$key];
        // the type from the file's own bytes, never from its name or what the browser says
        $ext = self::TYPES[strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()))] ?? null;
        if ($ext === null) {
            unset($this->files[$key]);
            Notification::make()->danger()->title(__('এটা অডিও ফাইল নয়। mp3, m4a, ogg, wav বা webm দিন।'))->send();

            return;
        }
        @mkdir(public_path(LearnAudio::DIR), 0755, true);
        $name = Str::random(24).'.'.$ext;
        copy($file->getRealPath(), public_path(LearnAudio::DIR.'/'.$name));
        $text = LearnCourse::nfc($text);
        $old = LearnAudio::where('text', $text)->first();
        if ($old) {
            @unlink(public_path($old->path));
        }
        LearnAudio::updateOrCreate(['text' => $text], ['path' => LearnAudio::DIR.'/'.$name, 'user_id' => auth()->id()]);
        AuditLog::record('learn.audio_uploaded', null, ['text' => $text]);
        unset($this->files[$key]);
        Notification::make()->success()->title(__('রেকর্ডিং রাখা হয়েছে').': '.$text)->send();
    }

    public function remove(string $key): void
    {
        $a = LearnAudio::all()->first(fn ($a) => self::key($a->text) === $key);
        if ($a) {
            @unlink(public_path($a->path));
            AuditLog::record('learn.audio_removed', null, ['text' => $a->text]);
            $a->delete();
            Notification::make()->success()->title(__('রেকর্ডিং মুছে ফেলা হয়েছে'))->send();
        }
    }
}
