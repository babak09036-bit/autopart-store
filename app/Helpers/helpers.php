<?php
declare(strict_types=1);

use App\Core\Config;

/** HTML-escape for output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Site-relative URL (respects the base path), e.g. url('products/abc') → /auto/products/abc */
function url(string $path = ''): string
{
    $base = (string) Config::get('app.base_path', '');
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:') || str_starts_with($path, '#')) {
        return $path;
    }
    return $base . '/' . ltrim($path, '/');
}

/** Absolute URL for canonical tags, sitemaps and structured data. */
function absolute_url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', (string) Config::get('app.url'));
    return $origin . url($path);
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function to_en_digits(string $value): string
{
    return strtr($value, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function fa_digits(string|int|float $value): string
{
    return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

/** 1250000 → ۱٬۲۵۰٬۰۰۰ */
function fa_number(int|float|string $value): string
{
    return fa_digits(number_format((float) $value, 0, '.', '٬'));
}

/** Persian-aware normalisation used for search: Arabic ye/kaf → Persian, digits → latin, no spaces/ZWNJ. */
function normalize_search(string $value): string
{
    $value = mb_strtolower(to_en_digits($value));
    $value = strtr($value, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'أ' => 'ا', 'إ' => 'ا']);
    $value = preg_replace('/[\x{064B}-\x{065F}]/u', '', $value) ?? $value;
    return preg_replace('/[\s\x{200C}\x{200D}\x{200E}\x{200F}]+/u', '', $value) ?? $value;
}

/** URL slug that keeps Persian letters: "روغن موتور 10W" → "روغن-موتور-10w" */
function slugify(string $text): string
{
    $text = mb_strtolower(to_en_digits(trim($text)));
    $text = strtr($text, ['ي' => 'ی', 'ك' => 'ک', "\u{200C}" => '-']);
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
    return trim(preg_replace('/-+/', '-', $text) ?? '', '-');
}

/** Converts a UTC datetime to a Persian (Jalali) date string for display. */
function fa_date(?string $utc, bool $withTime = false): string
{
    if (!$utc) {
        return '';
    }
    try {
        $dt = new DateTime($utc, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone((string) Config::get('app.timezone_display', 'Asia/Tehran')));
        if (class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter('fa_IR@calendar=persian', IntlDateFormatter::LONG, $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
                (string) Config::get('app.timezone_display'), IntlDateFormatter::TRADITIONAL);
            $out = $fmt->format($dt);
            if ($out !== false) {
                return $out;
            }
        }
        [$jy, $jm, $jd] = gregorian_to_jalali((int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j'));
        $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        $s = fa_digits($jd) . ' ' . $months[$jm - 1] . ' ' . fa_digits($jy);
        return $withTime ? $s . '، ساعت ' . fa_digits($dt->format('H:i')) : $s;
    } catch (Throwable) {
        return '';
    }
}

/** Standard Gregorian → Jalali conversion (no intl dependency). */
function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

/** Inline reference to the local SVG sprite. */
function icon(string $name, string $class = ''): string
{
    return '<svg class="icon ' . e($class) . '" aria-hidden="true" focusable="false"><use href="assets/icons/icons.svg#i-' . e($name) . '"></use></svg>';
}

function json_out(mixed $value): string
{
    return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}
