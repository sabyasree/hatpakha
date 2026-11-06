<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Writer-uploaded cover images (docs/SECURITY.md §5). The upload is never kept as sent:
 * the real type is sniffed, the picture is decoded and re-encoded with GD (drops EXIF/GPS and
 * anything hidden in the file), saved under a random name in storage/app/public/covers
 * (served as /storage/covers/...), plus WebP sizes for the responsive <picture>.
 */
final class CoverImage
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    public const MIN_WIDTH = 800;

    public const MAX_WIDTH = 2000;

    private const MAX_PIXELS = 40_000_000;

    /** Validates, re-encodes and attaches the cover; replaces (and deletes) the old one. */
    public static function attach(Post $post, UploadedFile $file): void
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['cover' => $m]);
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            $fail('ছবিটি ৮ MB-এর মধ্যে হতে হবে।');
        }
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $fail('শুধু JPG, PNG বা WebP ছবি দেওয়া যায়।');
        }
        [$w, $h, $type] = $info;
        if ($w * $h > self::MAX_PIXELS) {
            $fail('ছবিটি খুব বড়। একটু ছোট করে দিন।');
        }
        $img = self::load($file->getRealPath(), $type);
        if (! $img) {
            $fail('ছবিটি খোলা গেল না। অন্য একটি ছবি দিন।');
        }
        if (imagesx($img) < self::MIN_WIDTH) {
            $fail('ছবিটি অন্তত '.Bn::num(self::MIN_WIDTH).' পিক্সেল চওড়া হতে হবে।');
        }
        if (imagesx($img) > self::MAX_WIDTH) {
            $img = self::resize($img, self::MAX_WIDTH);
        }

        $dir = 'covers/'.now()->format('Y/m');
        $abs = storage_path('app/public/'.$dir);
        if (! is_dir($abs)) {
            mkdir($abs, 0755, true);
        }
        self::guardFolder();
        $name = Str::lower(Str::random(24)).'.jpg';
        // Flatten onto white (PNG/WebP transparency) and write a clean JPEG master.
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        imageinterlace($flat, true);
        if (! imagejpeg($flat, $abs.'/'.$name, 85)) {
            $fail('ছবিটি রাখা গেল না। একটু পরে আবার চেষ্টা করুন।');
        }
        $rel = 'storage/'.$dir.'/'.$name;
        foreach (Images::WIDTHS as $width) {
            $v = $width >= imagesx($flat) ? $flat : self::resize($flat, $width);
            @imagewebp($v, storage_path('app/public/'.Images::variantPath($dir.'/'.$name, $width)), 78);
            if ($width >= imagesx($flat)) {
                break;
            }
        }

        self::remove($post);
        $post->forceFill(['featured_image_path' => $rel, 'featured_image_width' => imagesx($flat), 'featured_image_height' => imagesy($flat)])->save();
    }

    /** Removes an uploaded cover (files too). Old WordPress images are never touched. */
    public static function remove(Post $post): void
    {
        $old = (string) $post->featured_image_path;
        if (str_starts_with($old, 'storage/covers/') && ! str_contains($old, '..')) {
            $base = storage_path('app/public/'.substr($old, strlen('storage/')));
            @unlink($base);
            foreach (Images::WIDTHS as $w) {
                @unlink(preg_replace('/\.jpg$/', '.w'.$w.'.webp', $base));
            }
        }
        if ($old !== '') {
            $post->forceFill(['featured_image_path' => null, 'featured_image_width' => null, 'featured_image_height' => null])->save();
        }
    }

    /** No script can ever run from the upload folder (LiteSpeed/Apache read this). */
    private static function guardFolder(): void
    {
        $f = storage_path('app/public/.htaccess');
        if (! is_file($f)) {
            @file_put_contents($f, "# Uploads are data, never code (docs/SECURITY.md §5)\n<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|pl|py|cgi|sh)$\">\nRequire all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes\n");
        }
    }

    private static function load(string $file, int $type): \GdImage|false
    {
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default => false,
        };
        if ($img && $type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            // Phone photos keep their rotation in EXIF, which re-encoding drops: apply it first.
            $o = (int) (@exif_read_data($file)['Orientation'] ?? 1);
            $img = match ($o) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
        }
        if ($img && ! imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }

        return $img;
    }

    private static function resize(\GdImage $src, int $width): \GdImage
    {
        $height = max(1, (int) round(imagesy($src) * $width / imagesx($src)));
        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, imagesx($src), imagesy($src));

        return $dst;
    }
}
