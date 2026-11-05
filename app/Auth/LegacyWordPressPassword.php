<?php

namespace App\Auth;

use SensitiveParameter;

/**
 * Verifies WordPress password hashes so migrated users keep their passwords
 * (docs/SECURITY.md §2). Supports:
 *  - phpass portable hashes  ($P$ / $H$)          — WordPress ≤ 6.7
 *  - WordPress 6.8+ hashes   ($wp$2y$…)           — bcrypt over HMAC-SHA384
 *  - plain bcrypt            ($2y$ / $2a$ / $2b$) — some plugins
 *  - plain md5 (32 hex)                           — very old WordPress
 * Verification only; we never create legacy hashes.
 */
final class LegacyWordPressPassword
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function check(#[SensitiveParameter] string $password, string $hash): bool
    {
        if ($hash === '' || strlen($password) > 4096) {
            return false;
        }

        if (str_starts_with($hash, '$wp')) {
            foreach (array_unique([$password, trim($password)]) as $candidate) {
                $prehashed = base64_encode(hash_hmac('sha384', $candidate, 'wp-sha384', true));
                if (password_verify($prehashed, substr($hash, 3))) {
                    return true;
                }
            }

            return false;
        }

        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return hash_equals($hash, self::phpass($password, $hash));
        }

        if (preg_match('/^\$2[aby]\$/', $hash)) {
            return password_verify($password, $hash);
        }

        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            return hash_equals(strtolower($hash), md5($password));
        }

        return false;
    }

    /** Re-computes a phpass portable hash using the salt and cost stored in $setting. */
    public static function phpass(#[SensitiveParameter] string $password, string $setting): string
    {
        $countLog2 = strpos(self::ITOA64, $setting[3] ?? '');
        if ($countLog2 === false || $countLog2 < 7 || $countLog2 > 30) {
            return '*';
        }
        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return '*';
        }

        $count = 1 << $countLog2;
        $hash = md5($salt.$password, true);
        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        return substr($setting, 0, 12).self::encode64($hash, 16);
    }

    private static function encode64(string $input, int $count): string
    {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= self::ITOA64[$value & 0x3F];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= self::ITOA64[($value >> 6) & 0x3F];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= self::ITOA64[($value >> 12) & 0x3F];
            if ($i++ >= $count) {
                break;
            }
            $output .= self::ITOA64[($value >> 18) & 0x3F];
        } while ($i < $count);

        return $output;
    }
}
