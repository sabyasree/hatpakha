<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The signature picture for certificates (D73). Like every upload: the real type is sniffed, the picture
 * is redrawn with GD (no metadata, no payload), saved under a random name. Near-white paper becomes
 * transparent, so a phone photo of a signature on white paper sits cleanly on every design.
 */
final class Signature
{
    public static function store(string $file): string
    {
        $info = @getimagesize($file);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || filesize($file) > 2 * 1024 * 1024) {
            throw ValidationException::withMessages(['signature' => 'শুধু ২ MB-এর মধ্যে JPG, PNG বা WebP ছবি।']);
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
        };
        if (! $src) {
            throw ValidationException::withMessages(['signature' => 'ছবিটি পড়া গেল না।']);
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, 600 / $w, 200 / $h);
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        // White paper → transparent; ink kept (and slightly darkened, so a grey photo still reads).
        for ($y = 0; $y < $nh; $y++) {
            for ($x = 0; $x < $nw; $x++) {
                $c = imagecolorsforindex($out, imagecolorat($out, $x, $y));
                $l = ($c['red'] + $c['green'] + $c['blue']) / 3;
                if ($c['alpha'] >= 120 || $l > 200) {
                    imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 255, 255, 255, 127));
                } else {
                    $a = (int) min(127, max(0, ($l - 80) / 120 * 127));
                    imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 19, 33, 44, $a));   // navy ink
                }
            }
        }
        $dir = public_path('media/signature');
        @mkdir($dir, 0755, true);
        CoverImage::guardFolder();
        $name = 'media/signature/'.Str::lower(Str::random(24)).'.png';
        imagepng($out, public_path($name), 9);

        return $name;
    }
}
