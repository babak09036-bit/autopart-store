<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\HttpException;

/**
 * Server-side validation. Rules are pipe-separated strings:
 *   'required|string|max:120', 'nullable|int|min:0', 'in:a,b', 'email', 'mobile', 'slug', 'url', 'date', 'bool', 'color', 'array'
 * validate() returns only the keys that have rules (mass-assignment guard) with normalised values.
 */
final class Validator
{
    /** Rules whose values are normalised to Latin digits before checking. */
    private const TECHNICAL = ['int', 'numeric', 'mobile', 'email', 'url', 'path', 'slug', 'date', 'color', 'bool', 'in', 'regex'];

    private const LABELS = [
        'required' => 'این فیلد الزامی است.',
        'string' => 'مقدار باید متن باشد.',
        'int' => 'مقدار باید عدد صحیح باشد.',
        'numeric' => 'مقدار باید عدد باشد.',
        'bool' => 'مقدار باید بله/خیر باشد.',
        'email' => 'ایمیل معتبر نیست.',
        'mobile' => 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.',
        'slug' => 'اسلاگ فقط می‌تواند شامل حروف، عدد و خط تیره باشد.',
        'url' => 'نشانی معتبر نیست.',
        'date' => 'تاریخ معتبر نیست.',
        'color' => 'رنگ باید به شکل #RRGGBB باشد.',
        'array' => 'مقدار باید فهرست باشد.',
        'path' => 'مسیر فایل معتبر نیست.',
    ];

    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        $clean = [];
        foreach ($rules as $field => $ruleString) {
            $ruleList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $present = array_key_exists($field, $data);
            $value = $data[$field] ?? null;
            if (is_string($value)) {
                // Persian/Arabic digits become Latin only in technical values; free text (names, articles, addresses) keeps them.
                // Passwords too, so the same password typed on a Persian or English keyboard matches.
                $technical = array_intersect(array_map(static fn ($r) => explode(':', (string) $r, 2)[0], $ruleList), self::TECHNICAL) || str_contains((string) $field, 'password');
                $value = trim($technical ? \to_en_digits($value) : $value);
            }
            $nullable = in_array('nullable', $ruleList, true);
            $sometimes = in_array('sometimes', $ruleList, true);
            if ($sometimes && !$present) {
                continue;
            }
            $empty = $value === null || $value === '' || $value === [];
            if ($empty) {
                if (in_array('required', $ruleList, true)) {
                    $errors[$field] = self::LABELS['required'];
                } elseif ($present || $nullable) {
                    $clean[$field] = in_array('bool', $ruleList, true) ? false : (in_array('array', $ruleList, true) ? [] : null);
                }
                continue;
            }
            $error = null;
            foreach ($ruleList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = self::check($name, $arg, $value);
                if ($error !== null) {
                    break;
                }
                $value = self::cast($name, $value);
            }
            if ($error !== null) {
                $errors[$field] = $error;
            } else {
                $clean[$field] = $value;
            }
        }
        if ($errors) {
            throw new HttpException(422, 'اطلاعات واردشده معتبر نیست.', $errors);
        }
        return $clean;
    }

    private static function check(string $name, ?string $arg, mixed $value): ?string
    {
        $len = is_string($value) ? mb_strlen($value) : null;
        return match ($name) {
            'required', 'nullable', 'sometimes', 'trim' => null,
            'string' => is_string($value) ? null : self::LABELS['string'],
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : self::LABELS['int'],
            'numeric' => is_numeric($value) ? null : self::LABELS['numeric'],
            'bool' => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false', 'on'], true) ? null : self::LABELS['bool'],
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) && mb_strlen((string) $value) <= 190 ? null : self::LABELS['email'],
            'mobile' => preg_match('/^09\d{9}$/', (string) $value) ? null : self::LABELS['mobile'],
            'slug' => preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', (string) $value) && mb_strlen((string) $value) <= 190 ? null : self::LABELS['slug'],
            'url' => self::isUrl((string) $value) ? null : self::LABELS['url'],
            'path' => self::isLocalPath((string) $value) ? null : self::LABELS['path'],
            'date' => strtotime((string) $value) !== false ? null : self::LABELS['date'],
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? null : self::LABELS['color'],
            'array' => is_array($value) ? null : self::LABELS['array'],
            'in' => in_array((string) $value, explode(',', (string) $arg), true) ? null : 'مقدار انتخاب‌شده مجاز نیست.',
            'min' => $len !== null && !is_numeric($value)
                ? ($len >= (int) $arg ? null : "حداقل {$arg} کاراکتر لازم است.")
                : ((is_numeric($value) && $value + 0 >= (float) $arg) ? null : "مقدار باید حداقل {$arg} باشد."),
            'max' => $len !== null && !is_numeric($value)
                ? ($len <= (int) $arg ? null : "حداکثر {$arg} کاراکتر مجاز است.")
                : (is_array($value) ? (count($value) <= (int) $arg ? null : "حداکثر {$arg} مورد مجاز است.")
                    : ((is_numeric($value) && $value + 0 <= (float) $arg) ? null : "مقدار باید حداکثر {$arg} باشد.")),
            'maxlen' => mb_strlen((string) $value) <= (int) $arg ? null : "حداکثر {$arg} کاراکتر مجاز است.",
            'minlen' => mb_strlen((string) $value) >= (int) $arg ? null : "حداقل {$arg} کاراکتر لازم است.",
            'regex' => preg_match((string) $arg, (string) $value) ? null : 'قالب مقدار درست نیست.',
            default => throw new \LogicException('Unknown validation rule ' . $name),
        };
    }

    private static function cast(string $name, mixed $value): mixed
    {
        return match ($name) {
            'int' => (int) $value,
            'numeric' => $value + 0,
            'bool' => in_array($value, [true, 1, '1', 'true', 'on'], true),
            'email' => mb_strtolower((string) $value),
            'date' => self::toUtc((string) $value),
            'color' => strtoupper((string) $value),
            default => $value,
        };
    }

    /** Admin forms send local (Tehran) wall-clock times; storage is UTC. */
    public static function toUtc(string $value): string
    {
        $tz = new \DateTimeZone((string) \App\Core\Config::get('app.timezone_display', 'Asia/Tehran'));
        $dt = new \DateTime($value, $tz);
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** UTC → local "Y-m-d\TH:i" for <input type="datetime-local">. */
    public static function toLocalInput(?string $utc): ?string
    {
        if (!$utc) {
            return null;
        }
        $dt = new \DateTime($utc, new \DateTimeZone('UTC'));
        return $dt->setTimezone(new \DateTimeZone((string) \App\Core\Config::get('app.timezone_display', 'Asia/Tehran')))->format('Y-m-d\TH:i');
    }

    /** http(s) URLs, site-relative paths and anchors are accepted; javascript:/data: are not. */
    public static function isUrl(string $value): bool
    {
        if ($value === '' || preg_match('/[\s<>"]/', $value)) {
            return false;
        }
        if (preg_match('#^https?://#i', $value)) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        }
        if (preg_match('#^(mailto:|tel:)#i', $value)) {
            return true;
        }
        return !preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) && !str_starts_with($value, '//');
    }

    /** Local asset/upload path without traversal. */
    public static function isLocalPath(string $value): bool
    {
        return (bool) preg_match('#^(assets|uploads)/[A-Za-z0-9_./-]+$#', $value) && !str_contains($value, '..');
    }
}
