<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Image uploads: checks the upload error, size, real MIME type (finfo) and image header,
 * then re-encodes the pixels with GD (drops metadata and any embedded payload),
 * stores the file under a random name and creates a thumbnail.
 * SVG uploads are refused because SVG can carry scripts.
 */
final class Uploader
{
    public const FOLDERS = ['products', 'banners', 'categories', 'brands', 'blog', 'pages', 'reviews', 'general'];

    /** @return array{path:string,thumb:?string,mime:string,size:int,width:int,height:int,original:string} */
    public static function image(array $file, string $folder, ?int $maxBytes = null): array
    {
        if (!in_array($folder, self::FOLDERS, true)) {
            throw new HttpException(422, 'پوشه مقصد معتبر نیست.');
        }
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new HttpException(422, 'فایل ارسالی معتبر نیست.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new HttpException(422, $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
                ? 'حجم فایل بیش از حد مجاز است.' : 'بارگذاری فایل ناموفق بود.');
        }
        if (!is_uploaded_file($file['tmp_name']) && PHP_SAPI !== 'cli') {
            throw new HttpException(422, 'فایل ارسالی معتبر نیست.');
        }
        $maxBytes ??= (int) Config::get('app.uploads.max_bytes');
        $size = (int) filesize($file['tmp_name']);
        if ($size <= 0 || $size > $maxBytes) {
            throw new HttpException(422, 'حجم فایل باید کمتر از ' . fa_number(round($maxBytes / 1048576, 1)) . ' مگابایت باشد.');
        }
        $allowed = Config::get('app.uploads.allowed_mimes');
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            throw new HttpException(422, 'فقط تصاویر JPG، PNG و WebP مجاز هستند.');
        }
        $info = @getimagesize($file['tmp_name']);
        if (!$info || $info['mime'] !== $mime || $info[0] < 1 || $info[1] < 1) {
            throw new HttpException(422, 'محتوای فایل تصویر معتبر نیست.');
        }
        if ($info[0] * $info[1] > (int) Config::get('app.uploads.max_pixels')) {
            throw new HttpException(422, 'ابعاد تصویر بیش از حد بزرگ است.');
        }

        $ext = $allowed[$mime];
        $relDir = 'uploads/' . $folder . '/' . gmdate('Y/m');
        $absDir = BASE_DIR . '/' . $relDir;
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Cannot create upload directory');
        }
        $name = bin2hex(random_bytes(16));
        $relPath = $relDir . '/' . $name . '.' . $ext;

        $image = self::load($file['tmp_name'], $mime);
        if ($image === null) {
            throw new HttpException(422, 'پردازش تصویر ممکن نیست.');
        }
        self::save($image, BASE_DIR . '/' . $relPath, $mime);

        $thumb = null;
        $thumbWidth = (int) Config::get('app.uploads.thumb_width', 480);
        if ($info[0] > $thumbWidth) {
            $height = (int) round($info[1] * $thumbWidth / $info[0]);
            $small = imagescale($image, $thumbWidth, $height, IMG_BICUBIC);
            if ($small) {
                $thumb = $relDir . '/' . $name . '-thumb.' . $ext;
                self::save($small, BASE_DIR . '/' . $thumb, $mime);
                imagedestroy($small);
            }
        }
        imagedestroy($image);

        return [
            'path' => $relPath,
            'thumb' => $thumb,
            'mime' => $mime,
            'size' => (int) filesize(BASE_DIR . '/' . $relPath),
            'width' => $info[0],
            'height' => $info[1],
            'original' => mb_substr(basename((string) ($file['name'] ?? 'image')), 0, 190),
        ];
    }

    /** Uploads into the media library and returns the media_files row. */
    public static function toLibrary(array $file, string $folder, ?string $alt, ?int $adminId): array
    {
        $saved = self::image($file, $folder);
        $id = Database::insert('media_files', [
            'path' => $saved['path'],
            'thumb_path' => $saved['thumb'],
            'original_name' => $saved['original'],
            'mime' => $saved['mime'],
            'size_bytes' => $saved['size'],
            'width' => $saved['width'],
            'height' => $saved['height'],
            'alt' => $alt,
            'folder' => $folder,
            'uploaded_by' => $adminId,
        ]);
        return Database::one('SELECT * FROM media_files WHERE id = :id', ['id' => $id]) ?? [];
    }

    /** Deletes a stored upload (only inside uploads/). */
    public static function delete(?string $path): void
    {
        if (!$path || !str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
            return;
        }
        $abs = BASE_DIR . '/' . $path;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    private static function load(string $file, string $mime): ?\GdImage
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new HttpException(500, 'افزونه GD روی سرور فعال نیست؛ بارگذاری تصویر ممکن نیست.');
        }
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png' => @imagecreatefrompng($file),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default => false,
        };
        if (!$img) {
            return null;
        }
        if ($mime !== 'image/jpeg') {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
        return $img;
    }

    private static function save(\GdImage $img, string $path, string $mime): void
    {
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($img, $path, 85),
            'image/png' => imagepng($img, $path, 6),
            'image/webp' => imagewebp($img, $path, 82),
        };
        if (!$ok) {
            throw new \RuntimeException('Cannot write image');
        }
    }
}
