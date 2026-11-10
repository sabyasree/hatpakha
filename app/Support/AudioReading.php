<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\EditorTask;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Writers' own readings (D67). The file's real type is checked from its bytes (not its name), it is
 * stored under public/media/audio with a random name (scripts blocked there), and it waits for the
 * owner before readers hear it.
 */
final class AudioReading
{
    public const MAX_MB = 25;

    /** Real content type → stored extension. */
    public const TYPES = ['audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'video/mp4' => 'm4a', 'audio/aac' => 'aac', 'audio/ogg' => 'ogg', 'audio/webm' => 'webm', 'video/webm' => 'webm'];

    public static function attach(Post $post, UploadedFile $file): void
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';
        if (! isset(self::TYPES[$mime]) || $file->getSize() > self::MAX_MB * 1024 * 1024) {
            throw ValidationException::withMessages(['audio' => 'MP3, M4A বা OGG ফাইল দিন, '.Bn::num((string) self::MAX_MB).' MB-এর মধ্যে।']);
        }
        $dir = 'audio/'.now()->format('Y/m');
        if (! is_dir(public_path('media/'.$dir))) {
            mkdir(public_path('media/'.$dir), 0755, true);
        }
        CoverImage::guardFolder();
        $name = Str::lower(Str::random(24)).'.'.self::TYPES[$mime];
        $file->move(public_path('media/'.$dir), $name);
        self::remove($post);
        $post->forceFill(['audio_path' => '/media/'.$dir.'/'.$name, 'audio_status' => 'pending'])->saveQuietly();
        AuditLog::record('post.audio_uploaded', $post);
        EditorTask::propose('audio', __('লেখকের পাঠ (অডিও): ').$post->title, [], $post);
    }

    public static function approve(Post $post): void
    {
        $post->forceFill(['audio_status' => 'approved'])->saveQuietly();
        AuditLog::record('post.audio_approved', $post);
        EditorTask::where('type', 'audio')->where('status', 'open')->where('subject_id', $post->id)->get()->each->close();
    }

    public static function remove(Post $post): void
    {
        if ($post->audio_path && str_starts_with($post->audio_path, '/media/audio/') && ! str_contains($post->audio_path, '..')) {
            @unlink(public_path(ltrim($post->audio_path, '/')));
        }
        $post->forceFill(['audio_path' => null, 'audio_status' => null])->saveQuietly();
        EditorTask::where('type', 'audio')->where('status', 'open')->where('subject_id', $post->id)->get()->each->close('dismissed');
    }
}
