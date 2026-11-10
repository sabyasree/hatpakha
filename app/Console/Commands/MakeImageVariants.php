<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Post;
use App\Support\Images;
use Illuminate\Console\Command;

/**
 * Creates WebP copies (480/960/1440 px wide) of every upload that posts and pages actually use.
 * Originals are never changed. Safe to re-run: existing copies are skipped. Run on the server:
 *   php artisan hatpakha:images
 */
class MakeImageVariants extends Command
{
    protected $signature = 'hatpakha:images {--limit=0 : Process only N images (testing)} {--force : Rebuild copies that already exist}';

    protected $description = 'Make responsive WebP copies of the images used by posts and pages';

    private const MAX_PIXELS = 40_000_000; // skip giant scans that would exhaust memory

    /** @var array<string,array{count:int,example:string}> */
    private array $reasons = [];

    private function recordFailure(array &$stats, string $why, string $rel): void
    {
        $stats['failed']++;
        $this->reasons[$why] ??= ['count' => 0, 'example' => $rel];
        $this->reasons[$why]['count']++;
    }

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('PHP GD with WebP support is not available on this server.');

            return self::FAILURE;
        }
        @ini_set('memory_limit', '512M');

        $paths = $this->usedImages();
        if ($limit = (int) $this->option('limit')) {
            $paths = array_slice($paths, 0, $limit);
        }
        $this->info(count($paths).' images used by posts and pages.');
        $bar = $this->output->createProgressBar(count($paths));
        $stats = ['made' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0, 'saved_kb' => 0];
        $this->reasons = [];
        $this->line('PHP memory_limit: '.ini_get('memory_limit').' · GD: '.(gd_info()['GD Version'] ?? '?')
            .' · JPEG '.((gd_info()['JPEG Support'] ?? false) ? 'yes' : 'NO').' · PNG '.((gd_info()['PNG Support'] ?? false) ? 'yes' : 'NO')
            .' · WebP '.((gd_info()['WebP Support'] ?? false) ? 'yes' : 'NO'));

        foreach ($paths as $rel) {
            $this->process($rel, $stats);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);
        $this->table(['WebP copies made', 'Already there', 'Original missing', 'Failed', 'Smaller by (MB)'],
            [[$stats['made'], $stats['skipped'], $stats['missing'], $stats['failed'], round($stats['saved_kb'] / 1024, 1)]]);
        if ($this->reasons) {
            $this->warn('Why images failed (count, reason, example):');
            $this->table(['Images', 'Reason', 'Example'], collect($this->reasons)->sortByDesc('count')
                ->map(fn ($r, $why) => [$r['count'], $why, $r['example']])->values()->all());
        }

        return self::SUCCESS;
    }

    /** @return array<int,string> unique relative paths under wp-content/uploads */
    private function usedImages(): array
    {
        $set = [];
        Post::query()->whereNotNull('featured_image_path')->pluck('featured_image_path')
            ->each(function ($p) use (&$set) {
                if ($rel = Images::relative('/'.$p)) {
                    $set[$rel] = true;
                }
            });
        foreach ([Post::query()->select('body_html'), Page::query()->select('body_html')] as $q) {
            foreach ($q->cursor() as $row) {
                preg_match_all('~<img\b[^>]*\ssrc="([^"]+)"~i', (string) $row->body_html, $m);
                foreach ($m[1] as $src) {
                    if ($rel = Images::relative(html_entity_decode($src))) {
                        $set[$rel] = true;
                    }
                }
            }
        }

        return array_keys($set);
    }

    /** @param array<string,int> $stats */
    private function process(string $rel, array &$stats): void
    {
        $file = public_path($rel);
        if (! is_file($file)) {
            $stats['missing']++;

            return;
        }
        $info = @getimagesize($file);
        if (! $info) {
            $this->recordFailure($stats, 'not a readable image ('.filesize($file).' bytes)', $rel);

            return;
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            $this->recordFailure($stats, 'too large (over 40 megapixels)', $rel);

            return;
        }
        [$w0] = $info;

        // Widths to make: each standard width smaller than the original, plus the original size if smaller than 1440.
        $widths = array_values(array_filter(Images::WIDTHS, fn ($w) => $w < $w0));
        if ($w0 < 1440) {
            $widths[] = min(1440, max(1, $w0));
        }
        $todo = [];
        foreach (array_unique($widths) as $w) {
            $std = $this->standardWidth($w);
            $out = public_path(Images::variantPath($rel, $std));
            if (! $this->option('force') && is_file($out) && filemtime($out) >= filemtime($file)) {
                $stats['skipped']++;

                continue;
            }
            $todo[$std] = [$w, $out];
        }
        if (! $todo) {
            return;
        }

        error_clear_last();
        try {
            $src = $this->load($file, $info[2]);
        } catch (\Throwable $e) {
            $src = false;
            $this->recordFailure($stats, 'load error: '.mb_substr($e->getMessage(), 0, 80), $rel);

            return;
        }
        if (! $src) {
            $this->recordFailure($stats, 'could not decode '.image_type_to_extension($info[2], false).': '.mb_substr(error_get_last()['message'] ?? 'unknown', 0, 80), $rel);

            return;
        }
        foreach ($todo as [$w, $out]) {
            $img = $w >= imagesx($src) ? $src : $this->resize($src, $w);
            if (! $img) {
                $this->recordFailure($stats, 'resize failed', $rel);

                continue;
            }
            imagealphablending($img, false);
            imagesavealpha($img, true);
            error_clear_last();
            if (! is_writable(dirname($out))) {
                $this->recordFailure($stats, 'folder not writable', $rel);
            } elseif (@imagewebp($img, $out, 78)) {
                $stats['made']++;
                $stats['saved_kb'] += max(0, (filesize($file) - filesize($out)) / 1024 / count($todo));
            } else {
                $this->recordFailure($stats, 'WebP write failed: '.mb_substr(error_get_last()['message'] ?? 'unknown', 0, 80), $rel);
            }
            if ($img !== $src) {
                imagedestroy($img);
            }
        }
        imagedestroy($src);
    }

    /**
     * The file name always uses a standard width (480/960/1440) so templates can find it; an image
     * narrower than that width is stored at its own size under the next standard name.
     */
    private function standardWidth(int $w): int
    {
        foreach (Images::WIDTHS as $std) {
            if ($w <= $std) {
                return $std;
            }
        }

        return end(Images::WIDTHS);
    }

    /**
     * Resize to a width, keeping the aspect ratio and transparency. imagecopyresampled works on every
     * GD build; imagescale(IMG_BICUBIC) returned false on Hostinger's GD for every image.
     */
    private function resize(\GdImage $src, int $width): \GdImage|false
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $height = max(1, (int) round($sh * $width / $sw));
        $dst = imagecreatetruecolor($width, $height);
        if (! $dst) {
            return false;
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefilledrectangle($dst, 0, 0, $width, $height, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        if (! imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $sw, $sh)) {
            imagedestroy($dst);

            return false;
        }

        return $dst;
    }

    private function load(string $file, int $type): \GdImage|false
    {
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            default => false,
        };
        if ($img && $type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            // Phone photos store rotation in EXIF; browsers apply it to the JPEG, so apply it to the WebP too.
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
}
