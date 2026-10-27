<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\Uploader;

final class MediaController extends AdminController
{
    public function index(Request $request): Response
    {
        $sql = 'SELECT m.*, a.full_name AS uploader FROM media_files m LEFT JOIN admin_users a ON a.id = m.uploaded_by WHERE 1 = 1';
        $params = [];
        if (in_array($f = (string) $request->query('folder', ''), Uploader::FOLDERS, true)) {
            $sql .= ' AND m.folder = :f';
            $params['f'] = $f;
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $sql .= ' AND (m.original_name LIKE :q1 OR m.alt LIKE :q2 OR m.path LIKE :q3)';
            $params += ['q1' => $this->like($q), 'q2' => $this->like($q), 'q3' => $this->like($q)];
        }
        $result = $this->paginate($sql, $params, $request, 'm.id DESC', 24);
        $result['items'] = array_map(static fn ($m) => $m + ['at' => fa_date($m['created_at'])], $result['items']);
        return $this->ok($result);
    }

    public function upload(Request $request): Response
    {
        $file = $request->files['file'] ?? null;
        if (!$file) {
            throw new HttpException(422, 'فایلی انتخاب نشده است.', ['file' => 'یک تصویر انتخاب کنید.']);
        }
        $data = $this->validate($request->all(), ['folder' => 'required|in:' . implode(',', Uploader::FOLDERS), 'alt' => 'nullable|string|maxlen:255']);
        $media = Uploader::toLibrary($file, $data['folder'], $data['alt'] ?? null, AdminAuth::id());
        ActivityLogger::admin('media.upload', 'media', (int) $media['id'], 'بارگذاری تصویر: ' . $media['original_name']);
        return $this->ok($media, 'تصویر بارگذاری شد.', 201);
    }

    public function update(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $data = $this->validate($request->all(), ['alt' => 'nullable|string|maxlen:255']);
        Database::update('media_files', ['alt' => $data['alt'] ?? null], 'id = :id', ['id' => $id]);
        return $this->ok(null, 'متن جایگزین ذخیره شد.');
    }

    public function destroy(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $media = Database::one('SELECT * FROM media_files WHERE id = :id', ['id' => $id]);
        if (!$media) {
            throw new HttpException(404, 'فایل یافت نشد.');
        }
        $usage = $this->usage($media['path']);
        if ($usage && $request->query('force') !== '1') {
            throw new HttpException(409, 'این تصویر در ' . implode('، ', $usage) . ' استفاده شده است. برای حذف قطعی دوباره تأیید کنید.', ['usage' => $usage]);
        }
        Database::query('DELETE FROM media_files WHERE id = :id', ['id' => $id]);
        Uploader::delete($media['path']);
        Uploader::delete($media['thumb_path']);
        ActivityLogger::admin('media.delete', 'media', $id, 'حذف تصویر: ' . $media['original_name']);
        return $this->ok(null, 'تصویر حذف شد.');
    }

    private function usage(string $path): array
    {
        $checks = [
            'محصولات' => 'SELECT 1 FROM product_images WHERE path = :p', 'بنرها' => 'SELECT 1 FROM banners WHERE image_desktop = :p OR image_mobile = :p2',
            'دسته‌بندی‌ها' => 'SELECT 1 FROM categories WHERE image_path = :p', 'برندها' => 'SELECT 1 FROM brands WHERE logo_path = :p',
            'مقالات' => 'SELECT 1 FROM blog_posts WHERE featured_image = :p OR content LIKE :p2', 'صفحات' => 'SELECT 1 FROM pages WHERE featured_image = :p OR content LIKE :p2',
        ];
        $used = [];
        foreach ($checks as $label => $sql) {
            $params = ['p' => $path] + (str_contains($sql, ':p2') ? ['p2' => str_contains($sql, 'LIKE') ? '%' . $path . '%' : $path] : []);
            if (Database::value($sql . ' LIMIT 1', $params)) {
                $used[] = $label;
            }
        }
        return $used;
    }
}
