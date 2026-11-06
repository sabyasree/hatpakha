<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Writer-uploaded cover images (docs/SECURITY.md §5). The upload is never kept as sent:
 * the real type is sniffed, the picture is decoded and re-encoded with GD (drops EXIF/GPS and
 * anything hidden in the file), saved under a random name in public/media/covers (a real folder:
 * Hostinger does not follow a symlink inside the symlinked web root), plus WebP sizes for <picture>.
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
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['cover' => 'ছবিটি ৮ MB-এর মধ্যে হতে হবে।']);
        }
        $saved = self::store($file->getRealPath());
        self::remove($post);
        $post->forceFill(['featured_image_path' => $saved['path'], 'featured_image_width' => $saved['width'], 'featured_image_height' => $saved['height'], 'image_is_ai' => false])->save();
    }

    /** AI illustration (D43): kept as a proposal until the owner approves it. */
    public static function propose(Post $post, string $file): void
    {
        $saved = self::store($file);
        self::discardProposed($post);
        $post->forceFill(['proposed_image_path' => $saved['path'], 'proposed_image_width' => $saved['width'], 'proposed_image_height' => $saved['height'], 'proposed_image_error' => null])->save();
    }

    /** Owner approved the proposal: it becomes the cover (the old uploaded cover, if any, is deleted). */
    public static function acceptProposed(Post $post): void
    {
        if (! $post->proposed_image_path) {
            return;
        }
        $p = [$post->proposed_image_path, $post->proposed_image_width, $post->proposed_image_height];
        $post->forceFill(['proposed_image_path' => null, 'proposed_image_width' => null, 'proposed_image_height' => null])->save();
        self::remove($post);
        $post->forceFill(['featured_image_path' => $p[0], 'featured_image_width' => $p[1], 'featured_image_height' => $p[2], 'image_is_ai' => true])->save();
    }

    public static function discardProposed(Post $post): void
    {
        if ($post->proposed_image_path) {
            self::deleteFiles($post->proposed_image_path);
            $post->forceFill(['proposed_image_path' => null, 'proposed_image_width' => null, 'proposed_image_height' => null])->save();
        }
    }

    /**
     * Sniff, decode, re-encode (no metadata, no payload), save under a random name with WebP sizes.
     *
     * @return array{path:string,width:int,height:int}
     */
    public static function store(string $file): array
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['cover' => $m]);
        if (! is_file($file) || filesize($file) > self::MAX_BYTES) {
            $fail('ছবিটি ৮ MB-এর মধ্যে হতে হবে।');
        }
        $info = @getimagesize($file);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $fail('শুধু JPG, PNG বা WebP ছবি দেওয়া যায়।');
        }
        [$w, $h, $type] = $info;
        if ($w * $h > self::MAX_PIXELS) {
            $fail('ছবিটি খুব বড়। একটু ছোট করে দিন।');
        }
        $img = self::load($file, $type);
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
        $abs = public_path('media/'.$dir);
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
        foreach (Images::WIDTHS as $width) {
            $v = $width >= imagesx($flat) ? $flat : self::resize($flat, $width);
            @imagewebp($v, public_path('media/'.Images::variantPath($dir.'/'.$name, $width)), 78);
            if ($width >= imagesx($flat)) {
                break;
            }
        }

        return ['path' => 'media/'.$dir.'/'.$name, 'width' => imagesx($flat), 'height' => imagesy($flat)];
    }

    /** Removes an uploaded cover (files too). Old WordPress images are never touched. */
    public static function remove(Post $post): void
    {
        $old = (string) $post->featured_image_path;
        self::deleteFiles($old);
        if ($old !== '') {
            $post->forceFill(['featured_image_path' => null, 'featured_image_width' => null, 'featured_image_height' => null, 'image_is_ai' => false])->save();
        }
    }

    /** Covers this app made (writer uploads + AI pictures); old WordPress files are never touched. */
    public static function isOurs(?string $path): bool
    {
        $path = (string) $path;

        return (str_starts_with($path, 'media/covers/') || str_starts_with($path, 'storage/covers/')) && ! str_contains($path, '..');
    }

    /** One-off move of covers saved under storage/ (not reachable on Hostinger) to public/media/. */
    public static function moveLegacy(): int
    {
        $moved = 0;
        foreach (\App\Models\Post::where('featured_image_path', 'like', 'storage/covers/%')->orWhere('proposed_image_path', 'like', 'storage/covers/%')->get() as $post) {
            foreach (['featured_image_path', 'proposed_image_path'] as $col) {
                $old = (string) $post->{$col};
                if (! str_starts_with($old, 'storage/covers/') || str_contains($old, '..')) {
                    continue;
                }
                $rel = substr($old, strlen('storage/'));            // covers/Y/m/name.jpg
                $from = storage_path('app/public/'.$rel);
                $to = public_path('media/'.$rel);
                if (! is_dir(dirname($to))) {
                    mkdir(dirname($to), 0755, true);
                }
                foreach (array_merge([$from], array_map(fn ($w) => preg_replace('/\.jpg$/', '.w'.$w.'.webp', $from), Images::WIDTHS)) as $f) {
                    if (is_file($f)) {
                        @rename($f, public_path('media/'.substr($f, strlen(storage_path('app/public/')))));
                    }
                }
                if (is_file($to)) {
                    $post->forceFill([$col => 'media/'.$rel])->saveQuietly();
                    $moved++;
                }
            }
        }
        self::guardFolder();

        return $moved;
    }

    public static function deleteFiles(string $path): void
    {
        if (self::isOurs($path)) {
            $base = str_starts_with($path, 'media/') ? public_path($path) : storage_path('app/public/'.substr($path, strlen('storage/')));
            @unlink($base);
            foreach (Images::WIDTHS as $w) {
                @unlink(preg_replace('/\.jpg$/', '.w'.$w.'.webp', $base));
            }
        }
    }

    /** No script can ever run from the upload folder (LiteSpeed/Apache read this). */
    public static function guardFolder(): void
    {
        $f = public_path('media/.htaccess');
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
