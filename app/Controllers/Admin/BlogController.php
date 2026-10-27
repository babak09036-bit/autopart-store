<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\BlogService;
use App\Services\HtmlSanitizer;
use App\Services\RedirectService;
use App\Validators\Validator;

final class BlogController extends AdminController
{
    private const RULES = [
        'title' => 'required|string|maxlen:190',
        'subtitle' => 'nullable|string|maxlen:255',
        'slug' => 'nullable|slug',
        'excerpt' => 'nullable|string|maxlen:500',
        'content' => 'nullable|string|maxlen:500000',
        'author_id' => 'nullable|int',
        'featured_image' => 'nullable|path',
        'featured_image_alt' => 'nullable|string|maxlen:255',
        'featured_image_caption' => 'nullable|string|maxlen:255',
        'status' => 'required|in:draft,scheduled,published,unpublished',
        'published_at' => 'nullable|date',
        'is_featured' => 'nullable|bool',
        'is_pillar' => 'nullable|bool',
        'allow_comments' => 'nullable|bool',
        'focus_keyword' => 'nullable|string|maxlen:120',
        'related_keywords' => 'nullable|string|maxlen:255',
        'category_ids' => 'nullable|array',
        'tag_names' => 'nullable|array|max:20',
        'related_post_ids' => 'nullable|array|max:10',
        'product_ids' => 'nullable|array|max:12',
    ];

    public function index(Request $request): Response
    {
        $sql = 'SELECT bp.id, bp.title, bp.slug, bp.status, bp.is_featured, bp.is_pillar, bp.published_at, bp.updated_at, bp.view_count, a.name AS author_name,
                (SELECT GROUP_CONCAT(c.name SEPARATOR "، ") FROM blog_post_categories x JOIN blog_categories c ON c.id = x.category_id WHERE x.post_id = bp.id) AS categories,
                (SELECT COUNT(*) FROM blog_comments bc WHERE bc.post_id = bp.id AND bc.status = "pending") AS pending_comments
            FROM blog_posts bp LEFT JOIN blog_authors a ON a.id = bp.author_id WHERE 1 = 1';
        $params = [];
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids', ''))));
        if ($ids) {
            $sql .= ' AND bp.id IN ' . Database::in('ids', $ids, $params);
        }
        if (in_array($s = (string) $request->query('status', ''), ['draft', 'scheduled', 'published', 'unpublished'], true)) {
            $sql .= ' AND bp.status = :s';
            $params['s'] = $s;
        }
        if (ctype_digit((string) $request->query('category_id', ''))) {
            $sql .= ' AND EXISTS (SELECT 1 FROM blog_post_categories x WHERE x.post_id = bp.id AND x.category_id = :c)';
            $params['c'] = (int) $request->query('category_id');
        }
        if (ctype_digit((string) $request->query('author_id', ''))) {
            $sql .= ' AND bp.author_id = :a';
            $params['a'] = (int) $request->query('author_id');
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $sql .= ' AND (bp.title LIKE :q1 OR bp.slug LIKE :q2)';
            $params += ['q1' => $this->like($q), 'q2' => $this->like($q)];
        }
        $result = $this->paginate($sql, $params, $request, $this->order($request, ['published_at' => 'bp.published_at', 'updated_at' => 'bp.updated_at', 'views' => 'bp.view_count', 'title' => 'bp.title'], 'bp.id DESC'));
        $result['items'] = array_map(static fn ($p) => $p + ['published_at_fa' => fa_date($p['published_at'], true)], $result['items']);
        return $this->ok($result);
    }

    public function show(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $p = Database::one('SELECT * FROM blog_posts WHERE id = :id', ['id' => $id]);
        if (!$p) {
            throw new HttpException(404, 'مقاله یافت نشد.');
        }
        $p['published_at_local'] = Validator::toLocalInput($p['published_at']);
        $p['category_ids'] = array_map('intval', array_column(Database::all('SELECT category_id FROM blog_post_categories WHERE post_id = :id', ['id' => $id]), 'category_id'));
        $p['tag_names'] = array_column(Database::all('SELECT t.name FROM blog_post_tags x JOIN blog_tags t ON t.id = x.tag_id WHERE x.post_id = :id', ['id' => $id]), 'name');
        $p['related_post_ids'] = array_map('intval', array_column(Database::all('SELECT related_post_id FROM blog_post_relations WHERE post_id = :id', ['id' => $id]), 'related_post_id'));
        $p['product_ids'] = array_map('intval', array_column(Database::all('SELECT product_id FROM blog_post_products WHERE post_id = :id', ['id' => $id]), 'product_id'));
        $p['revisions'] = array_map(static fn ($r) => $r + ['at' => fa_date($r['created_at'], true)],
            Database::all('SELECT r.id, r.title, r.created_at, a.full_name AS admin_name FROM blog_post_revisions r LEFT JOIN admin_users a ON a.id = r.admin_user_id WHERE r.post_id = :id ORDER BY r.id DESC LIMIT 30', ['id' => $id]));
        return $this->ok($p);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        $id = Database::transaction(function () use ($data) {
            $id = Database::insert('blog_posts', $this->columns($data, null));
            $this->saveRelations($id, $data);
            return $id;
        });
        ActivityLogger::admin($data['status'] === 'published' ? 'post.publish' : 'create', 'post', $id, 'ایجاد مقاله: ' . $data['title'], ['status' => $data['status']]);
        return $this->ok(['id' => $id], 'مقاله ذخیره شد.', 201);
    }

    public function update(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $before = Database::one('SELECT * FROM blog_posts WHERE id = :id', ['id' => $id]);
        if (!$before) {
            throw new HttpException(404, 'مقاله یافت نشد.');
        }
        $data = $this->validated($request, $before);
        $cols = $this->columns($data, $before);
        Database::transaction(function () use ($id, $data, $cols, $before) {
            if ($before['content'] !== $cols['content'] || $before['title'] !== $cols['title']) {
                Database::insert('blog_post_revisions', ['post_id' => $id, 'title' => $before['title'], 'excerpt' => $before['excerpt'], 'content' => $before['content'], 'admin_user_id' => AdminAuth::id()]);
            }
            Database::update('blog_posts', $cols, 'id = :id', ['id' => $id]);
            $this->saveRelations($id, $data);
            if ($cols['slug'] !== $before['slug']) {
                RedirectService::slugChanged('blog', $before['slug'], $cols['slug']);
            }
        });
        $action = $before['status'] !== $cols['status'] ? match ($cols['status']) { 'published' => 'post.publish', 'unpublished' => 'post.unpublish', 'scheduled' => 'post.schedule', default => 'update' } : 'update';
        $diff = ActivityLogger::diff($before, $cols);
        unset($diff['content']);
        ActivityLogger::admin($action, 'post', $id, 'ویرایش مقاله: ' . $cols['title'], $diff);
        return $this->ok(null, 'مقاله ذخیره شد.');
    }

    public function destroy(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $row = Database::one('SELECT id, title, slug FROM blog_posts WHERE id = :id', ['id' => $id]);
        if (!$row) {
            throw new HttpException(404, 'مقاله یافت نشد.');
        }
        Database::query('DELETE FROM blog_posts WHERE id = :id', ['id' => $id]);
        Database::query("DELETE FROM seo_metadata WHERE entity_type = 'post' AND entity_id = :id", ['id' => $id]);
        ActivityLogger::admin('post.delete', 'post', $id, 'حذف مقاله: ' . $row['title'], $row);
        return $this->ok(null, 'مقاله حذف شد.');
    }

    public function restoreRevision(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $rev = Database::one('SELECT * FROM blog_post_revisions WHERE id = :r AND post_id = :p', ['r' => $this->intParam($request, 'revision'), 'p' => $id]);
        if (!$rev) {
            throw new HttpException(404, 'نسخه یافت نشد.');
        }
        $current = Database::one('SELECT title, excerpt, content FROM blog_posts WHERE id = :id', ['id' => $id]);
        Database::transaction(static function () use ($id, $rev, $current) {
            Database::insert('blog_post_revisions', ['post_id' => $id, 'title' => $current['title'], 'excerpt' => $current['excerpt'], 'content' => $current['content'], 'admin_user_id' => AdminAuth::id()]);
            Database::update('blog_posts', ['title' => $rev['title'], 'excerpt' => $rev['excerpt'], 'content' => HtmlSanitizer::clean($rev['content']),
                'reading_minutes' => BlogService::readingMinutes((string) $rev['content'])], 'id = :id', ['id' => $id]);
        });
        ActivityLogger::admin('post.restore', 'post', $id, 'بازیابی نسخه قبلی مقاله', ['revision' => $rev['id']]);
        return $this->ok(null, 'نسخه انتخاب‌شده بازیابی شد.');
    }

    private function validated(Request $request, ?array $before): array
    {
        $data = Validator::validate($request->all(), self::RULES);
        if ($data['status'] === 'scheduled' && (empty($data['published_at']) || $data['published_at'] <= now_utc())) {
            throw new HttpException(422, 'برای زمان‌بندی، زمان انتشار در آینده را مشخص کنید.', ['published_at' => 'زمان انتشار باید در آینده باشد.']);
        }
        if (!empty($data['author_id']) && !Database::value('SELECT 1 FROM blog_authors WHERE id = :id', ['id' => $data['author_id']])) {
            throw new HttpException(422, 'نویسنده یافت نشد.', ['author_id' => 'نویسنده نامعتبر است.']);
        }
        $data['content'] = HtmlSanitizer::clean($data['content'] ?? '');
        $slug = $data['slug'] ?: slugify($data['title']);
        $candidate = $slug;
        for ($i = 2; Database::value('SELECT 1 FROM blog_posts WHERE slug = :s' . ($before ? ' AND id <> :id' : ''), $before ? ['s' => $candidate, 'id' => $before['id']] : ['s' => $candidate]); $i++) {
            $candidate = $slug . '-' . $i;
        }
        $data['slug'] = $candidate;
        return $data;
    }

    private function columns(array $d, ?array $before): array
    {
        $publishedAt = $d['published_at'] ?? null;
        if ($d['status'] === 'published') {
            $publishedAt = $publishedAt && $publishedAt <= now_utc() ? $publishedAt : ($before['published_at'] ?? null ?: now_utc());
            if ($publishedAt > now_utc()) {
                $publishedAt = now_utc();
            }
        }
        return [
            'title' => $d['title'], 'subtitle' => $d['subtitle'] ?? null, 'slug' => $d['slug'], 'excerpt' => $d['excerpt'] ?? null,
            'content' => $d['content'], 'author_id' => $d['author_id'] ?: null, 'featured_image' => $d['featured_image'] ?? null,
            'featured_image_alt' => $d['featured_image_alt'] ?? null, 'featured_image_caption' => $d['featured_image_caption'] ?? null,
            'status' => $d['status'], 'published_at' => $publishedAt,
            'is_featured' => !empty($d['is_featured']) ? 1 : 0, 'is_pillar' => !empty($d['is_pillar']) ? 1 : 0,
            'allow_comments' => !empty($d['allow_comments']) ? 1 : 0,
            'focus_keyword' => $d['focus_keyword'] ?? null, 'related_keywords' => $d['related_keywords'] ?? null,
            'reading_minutes' => BlogService::readingMinutes($d['content']),
        ];
    }

    private function saveRelations(int $id, array $d): void
    {
        Database::query('DELETE FROM blog_post_categories WHERE post_id = :id', ['id' => $id]);
        foreach (array_unique(array_map('intval', (array) ($d['category_ids'] ?? []))) as $c) {
            Database::query('INSERT IGNORE INTO blog_post_categories (post_id, category_id) SELECT :p, id FROM blog_categories WHERE id = :c', ['p' => $id, 'c' => $c]);
        }
        Database::query('DELETE FROM blog_post_tags WHERE post_id = :id', ['id' => $id]);
        foreach ((array) ($d['tag_names'] ?? []) as $name) {
            $name = mb_substr(trim((string) $name), 0, 80);
            if ($name === '') {
                continue;
            }
            $slug = slugify($name);
            Database::query('INSERT IGNORE INTO blog_tags (name, slug) VALUES (:n, :s)', ['n' => $name, 's' => $slug]);
            Database::query('INSERT IGNORE INTO blog_post_tags (post_id, tag_id) SELECT :p, id FROM blog_tags WHERE slug = :s', ['p' => $id, 's' => $slug]);
        }
        Database::query('DELETE FROM blog_post_relations WHERE post_id = :id', ['id' => $id]);
        foreach (array_unique(array_map('intval', (array) ($d['related_post_ids'] ?? []))) as $r) {
            if ($r !== $id) {
                Database::query('INSERT IGNORE INTO blog_post_relations (post_id, related_post_id) SELECT :p, id FROM blog_posts WHERE id = :r', ['p' => $id, 'r' => $r]);
            }
        }
        Database::query('DELETE FROM blog_post_products WHERE post_id = :id', ['id' => $id]);
        foreach (array_unique(array_map('intval', (array) ($d['product_ids'] ?? []))) as $pid) {
            Database::query('INSERT IGNORE INTO blog_post_products (post_id, product_id) SELECT :p, id FROM products WHERE id = :x', ['p' => $id, 'x' => $pid]);
        }
    }

    /* ---------------- Comments ---------------- */

    public function comments(Request $request): Response
    {
        $sql = 'SELECT c.id, c.post_id, c.parent_id, c.author_name, c.author_email, c.body, c.status, c.report_count, c.admin_user_id, c.created_at, bp.title AS post_title
            FROM blog_comments c JOIN blog_posts bp ON bp.id = c.post_id WHERE 1 = 1';
        $params = [];
        if (in_array($s = (string) $request->query('status', ''), ['pending', 'approved', 'rejected', 'spam'], true)) {
            $sql .= ' AND c.status = :s';
            $params['s'] = $s;
        }
        if ($request->query('reported') === '1') {
            $sql .= ' AND c.report_count > 0';
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $sql .= ' AND (c.body LIKE :q1 OR c.author_name LIKE :q2)';
            $params += ['q1' => $this->like($q), 'q2' => $this->like($q)];
        }
        $result = $this->paginate($sql, $params, $request, 'c.id DESC');
        $result['items'] = array_map(fn ($c) => $c + ['at' => fa_date($c['created_at'], true), 'author_email' => AdminAuth::can('customers.sensitive') ? $c['author_email'] : self::maskEmail($c['author_email'])], $result['items']);
        return $this->ok($result);
    }

    public function updateComment(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $data = $this->validate($request->all(), ['status' => 'required|in:pending,approved,rejected,spam']);
        if (!Database::value('SELECT 1 FROM blog_comments WHERE id = :id', ['id' => $id])) {
            throw new HttpException(404, 'دیدگاه یافت نشد.');
        }
        Database::update('blog_comments', ['status' => $data['status'], 'report_count' => 0], 'id = :id', ['id' => $id]);
        ActivityLogger::admin('comment.' . $data['status'], 'blog_comment', $id, 'تغییر وضعیت دیدگاه', $data);
        return $this->ok(null, 'دیدگاه ذخیره شد.');
    }

    public function replyComment(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $comment = Database::one('SELECT id, post_id, parent_id FROM blog_comments WHERE id = :id', ['id' => $id]);
        if (!$comment) {
            throw new HttpException(404, 'دیدگاه یافت نشد.');
        }
        $data = $this->validate($request->all(), ['body' => 'required|string|minlen:2|maxlen:2000']);
        $admin = AdminAuth::admin();
        $replyId = Database::insert('blog_comments', [
            'post_id' => $comment['post_id'], 'parent_id' => $comment['parent_id'] ?: $comment['id'], 'admin_user_id' => $admin['id'],
            'author_name' => 'پاسخ ' . (\App\Services\SettingsService::get('store_name') ?: 'فروشگاه'), 'body' => $data['body'], 'status' => 'approved',
        ]);
        Database::query("UPDATE blog_comments SET status = 'approved' WHERE id = :id AND status = 'pending'", ['id' => $id]);
        ActivityLogger::admin('comment.reply', 'blog_comment', $replyId, 'پاسخ به دیدگاه', ['to' => $id]);
        return $this->ok(['id' => $replyId], 'پاسخ ثبت و منتشر شد.', 201);
    }

    public function destroyComment(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        Database::query('DELETE FROM blog_comments WHERE id = :id', ['id' => $id]);
        ActivityLogger::admin('comment.delete', 'blog_comment', $id, 'حذف دیدگاه');
        return $this->ok(null, 'دیدگاه حذف شد.');
    }
}
