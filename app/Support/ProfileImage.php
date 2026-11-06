<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Writer profile photo and cover (D59). Same safety as covers (D47): the file is sniffed, decoded and
 * re-encoded by GD (nothing of the original upload is kept), cropped to shape, saved under
 * public/media/authors (scripts blocked there).
 */
final class ProfileImage
{
    public const SHAPES = ['avatar' => [480, 480], 'cover' => [1500, 500]];

    public static function store(UploadedFile $file, string $kind): string
    {
        $field = $kind;
        $fail = fn (string $m) => throw ValidationException::withMessages([$field => $m]);
        [$tw, $th] = self::SHAPES[$kind];
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $fail('শুধু JPG, PNG বা WebP ছবি দেওয়া যায়।');
        }
        if ($info[0] * $info[1] > 40_000_000) {
            $fail('ছবিটি খুব বড়। একটু ছোট করে দিন।');
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            default => @imagecreatefromwebp($file->getRealPath()),
        };
        if (! $src) {
            $fail('ছবিটি খোলা গেল না। অন্য একটি ছবি দিন।');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < $tw / 2 || $h < $th / 2) {
            $fail($kind === 'avatar' ? 'ছবিটি অন্তত ২৪০×২৪০ পিক্সেল হতে হবে।' : 'প্রচ্ছদের ছবিটি অন্তত ৭৫০ পিক্সেল চওড়া হতে হবে।');
        }
        // Centre crop to the shape, then scale down (never up beyond the source).
        $ratio = $tw / $th;
        [$cw, $ch] = $w / $h > $ratio ? [(int) round($h * $ratio), $h] : [$w, (int) round($w / $ratio)];
        $outW = min($tw, $cw);
        $outH = (int) round($outW / $ratio);
        $out = imagecreatetruecolor($outW, $outH);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $src, 0, 0, (int) (($w - $cw) / 2), (int) (($h - $ch) / 2), $outW, $outH, $cw, $ch);

        $dir = 'authors/'.now()->format('Y/m');
        $abs = public_path('media/'.$dir);
        if (! is_dir($abs)) {
            mkdir($abs, 0755, true);
        }
        CoverImage::guardFolder();
        $name = Str::lower(Str::random(24)).'.jpg';
        imageinterlace($out, true);
        if (! imagejpeg($out, $abs.'/'.$name, 86)) {
            $fail('ছবিটি রাখা গেল না। একটু পরে আবার চেষ্টা করুন।');
        }
        if (function_exists('imagewebp')) {
            @imagewebp($out, $abs.'/'.substr($name, 0, -4).'.webp', 82);
        }

        return '/media/'.$dir.'/'.$name;
    }

    public static function remove(?string $path): void
    {
        if ($path && str_starts_with($path, '/media/authors/') && ! str_contains($path, '..')) {
            @unlink(public_path(ltrim($path, '/')));
            @unlink(public_path(ltrim(substr($path, 0, -4).'.webp', '/')));
        }
    }

    public static function webp(?string $path): ?string
    {
        return $path && is_file(public_path(ltrim(substr($path, 0, -4).'.webp', '/'))) ? substr($path, 0, -4).'.webp' : null;
    }
}
