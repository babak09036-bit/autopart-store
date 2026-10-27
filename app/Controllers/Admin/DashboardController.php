<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\BlogService;
use App\Services\CatalogService;

/** Dashboard and reports — every figure is computed from the database. Revenue counts paid-and-later statuses only. */
final class DashboardController extends AdminController
{
    private const SOLD = "('paid','processing','ready_to_ship','shipped','delivered')";

    public function dashboard(): Response
    {
        $tehranMidnightUtc = (new \DateTime('today', new \DateTimeZone('Asia/Tehran')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $monthStartUtc = (new \DateTime('first day of this month 00:00', new \DateTimeZone('Asia/Tehran')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $canOrders = AdminAuth::can('orders.view');
        $canSensitive = AdminAuth::can('customers.sensitive');

        $data = [
            'cards' => [
                'sales_today' => $canOrders ? (float) Database::value('SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE status IN ' . self::SOLD . ' AND placed_at >= :d', ['d' => $tehranMidnightUtc]) : null,
                'sales_month' => $canOrders ? (float) Database::value('SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE status IN ' . self::SOLD . ' AND placed_at >= :d', ['d' => $monthStartUtc]) : null,
                'orders_total' => $canOrders ? (int) Database::value('SELECT COUNT(*) FROM orders') : null,
                'orders_new' => $canOrders ? (int) Database::value("SELECT COUNT(*) FROM orders WHERE status IN ('pending_payment','paid','processing')") : null,
                'customers' => AdminAuth::can('customers.view') ? (int) Database::value('SELECT COUNT(*) FROM users') : null,
                'products' => (int) Database::value("SELECT COUNT(*) FROM products WHERE status = 'published'"),
                'low_stock' => (int) Database::value('SELECT COUNT(*) FROM (SELECT ' . CatalogService::STOCK_SQL . " AS s FROM products p WHERE p.status = 'published') t WHERE t.s <= 5"),
                'pending_reviews' => AdminAuth::can('reviews.view') ? (int) Database::value("SELECT COUNT(*) FROM product_reviews WHERE status = 'pending'") : null,
                'unread_messages' => AdminAuth::can('messages.manage') ? (int) Database::value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'") : null,
                'active_banners' => (int) Database::value('SELECT COUNT(*) FROM banners WHERE is_active = 1 AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at > UTC_TIMESTAMP())'),
                'posts_published' => (int) Database::value('SELECT COUNT(*) FROM blog_posts bp WHERE ' . BlogService::LIVE),
                'posts_draft' => (int) Database::value("SELECT COUNT(*) FROM blog_posts WHERE status IN ('draft','unpublished')"),
            ],
            'sales_chart' => $canOrders ? $this->dailySales(30) : [],
            'top_products' => $this->topProducts(5),
            'low_stock' => Database::all('SELECT * FROM (SELECT p.id, p.name, ' . CatalogService::STOCK_SQL . " AS stock FROM products p WHERE p.status = 'published') t WHERE t.stock <= 5 ORDER BY t.stock LIMIT 8"),
            'latest_orders' => $canOrders ? array_map(fn ($o) => $o + ['placed_at_fa' => fa_date($o['placed_at'], true),
                'customer_name' => $canSensitive ? $o['customer_name'] : mb_substr($o['customer_name'], 0, 1) . '***'],
                Database::all('SELECT id, order_number, customer_name, grand_total, status, placed_at FROM orders ORDER BY id DESC LIMIT 6')) : [],
            'latest_reviews' => AdminAuth::can('reviews.view') ? array_map(static fn ($r) => $r + ['at' => fa_date($r['created_at'])],
                Database::all('SELECT r.id, r.title, r.rating, r.status, r.created_at, p.name AS product FROM product_reviews r JOIN products p ON p.id = r.product_id ORDER BY r.id DESC LIMIT 5')) : [],
            'latest_messages' => AdminAuth::can('messages.manage') ? array_map(static fn ($m) => $m + ['at' => fa_date($m['created_at'])],
                Database::all('SELECT id, name, subject, status, created_at FROM contact_messages ORDER BY id DESC LIMIT 5')) : [],
        ];
        return $this->ok($data);
    }

    /** Daily totals in Tehran calendar days. */
    private function dailySales(int $days): array
    {
        $rows = Database::all("SELECT DATE(CONVERT_TZ(placed_at, '+00:00', '+03:30')) AS d, COUNT(*) AS orders, SUM(grand_total) AS total
            FROM orders WHERE status IN " . self::SOLD . ' AND placed_at >= UTC_TIMESTAMP() - INTERVAL :n DAY GROUP BY d ORDER BY d', ['n' => $days]);
        $byDay = array_column($rows, null, 'd');
        $out = [];
        $tz = new \DateTimeZone('Asia/Tehran');
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = (new \DateTime("-{$i} days", $tz))->format('Y-m-d');
            [$jy, $jm, $jd] = gregorian_to_jalali(...array_map('intval', explode('-', $date)));
            $out[] = ['date' => $date, 'label' => fa_digits($jm . '/' . $jd), 'orders' => (int) ($byDay[$date]['orders'] ?? 0), 'total' => (float) ($byDay[$date]['total'] ?? 0)];
        }
        return $out;
    }

    private function topProducts(int $limit, ?string $from = null): array
    {
        $params = [];
        $where = 'o.status IN ' . self::SOLD;
        if ($from) {
            $where .= ' AND o.placed_at >= :from';
            $params['from'] = $from;
        }
        return Database::all("SELECT oi.product_id, oi.product_name AS name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS revenue
            FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE {$where} GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC LIMIT {$limit}", $params);
    }

    public function reports(Request $request): Response
    {
        $range = max(7, min(365, (int) $request->query('days', 30)));
        $monthly = Database::all("SELECT DATE_FORMAT(CONVERT_TZ(placed_at, '+00:00', '+03:30'), '%Y-%m') AS m, COUNT(*) AS orders, SUM(grand_total) AS total, AVG(grand_total) AS avg_total
            FROM orders WHERE status IN " . self::SOLD . ' AND placed_at >= UTC_TIMESTAMP() - INTERVAL 12 MONTH GROUP BY m ORDER BY m');
        $from = gmdate('Y-m-d H:i:s', time() - $range * 86400);
        $summary = Database::one('SELECT COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS total, COALESCE(AVG(grand_total), 0) AS avg_total, COALESCE(SUM(discount_total), 0) AS discounts
            FROM orders WHERE status IN ' . self::SOLD . ' AND placed_at >= :f', ['f' => $from]);
        $statusCounts = Database::all('SELECT status, COUNT(*) AS c FROM orders GROUP BY status');
        return $this->ok([
            'range_days' => $range,
            'summary' => ['orders' => (int) $summary['orders'], 'total' => (float) $summary['total'], 'average' => round((float) $summary['avg_total']), 'discounts' => (float) $summary['discounts']],
            'daily' => $this->dailySales($range),
            'monthly' => array_map(static function ($m) {
                [$y, $mo] = array_map('intval', explode('-', $m['m']));
                [$jy, $jm] = gregorian_to_jalali($y, $mo, 15);
                return ['label' => fa_digits($jy . '/' . $jm), 'orders' => (int) $m['orders'], 'total' => (float) $m['total'], 'average' => round((float) $m['avg_total'])];
            }, $monthly),
            'top_products' => $this->topProducts(10, $from),
            'status_counts' => $statusCounts,
            'low_stock' => Database::all('SELECT * FROM (SELECT p.id, p.name, ' . CatalogService::STOCK_SQL . " AS stock FROM products p WHERE p.status = 'published') t WHERE t.stock <= 5 ORDER BY t.stock"),
            'new_customers' => AdminAuth::can('customers.view') ? array_map(static fn ($u) => ['id' => (int) $u['id'], 'name' => $u['full_name'], 'at' => fa_date($u['created_at'])],
                Database::all('SELECT id, full_name, created_at FROM users ORDER BY id DESC LIMIT 10')) : [],
            'pending_orders' => (int) Database::value("SELECT COUNT(*) FROM orders WHERE status IN ('paid','processing')"),
            'pending_reviews' => (int) Database::value("SELECT COUNT(*) FROM product_reviews WHERE status = 'pending'"),
            'posts' => ['published' => (int) Database::value('SELECT COUNT(*) FROM blog_posts bp WHERE ' . BlogService::LIVE), 'draft' => (int) Database::value("SELECT COUNT(*) FROM blog_posts WHERE status IN ('draft','unpublished')"),
                'scheduled' => (int) Database::value("SELECT COUNT(*) FROM blog_posts WHERE status = 'scheduled' AND published_at > UTC_TIMESTAMP()")],
            'active_banners' => (int) Database::value('SELECT COUNT(*) FROM banners WHERE is_active = 1 AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at > UTC_TIMESTAMP())'),
            'unread_messages' => (int) Database::value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"),
        ]);
    }

    /* ---------------- Notifications & activity log ---------------- */

    public function notifications(Request $request): Response
    {
        $id = AdminAuth::id();
        $rows = Database::all("SELECT id, type, title, body, link, read_at, created_at FROM notifications
            WHERE recipient_type = 'admin' AND (recipient_id IS NULL OR recipient_id = :id) ORDER BY id DESC LIMIT 50", ['id' => $id]);
        return $this->ok(array_map(static fn ($n) => $n + ['at' => fa_date($n['created_at'], true)], $rows));
    }

    public function readNotifications(): Response
    {
        Database::query("UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE recipient_type = 'admin' AND (recipient_id IS NULL OR recipient_id = :id) AND read_at IS NULL", ['id' => AdminAuth::id()]);
        return $this->ok(null, 'همه اعلان‌ها خوانده شد.');
    }

    public function activity(Request $request): Response
    {
        $sql = 'SELECT l.id, l.action, l.entity_type, l.entity_id, l.description, l.changes, l.ip, l.created_at, a.full_name AS admin_name
            FROM admin_activity_logs l LEFT JOIN admin_users a ON a.id = l.admin_user_id WHERE 1 = 1';
        $params = [];
        if ($t = (string) $request->query('entity_type', '')) {
            $sql .= ' AND l.entity_type = :t';
            $params['t'] = $t;
        }
        if (ctype_digit((string) $request->query('admin_id', ''))) {
            $sql .= ' AND l.admin_user_id = :a';
            $params['a'] = (int) $request->query('admin_id');
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $sql .= ' AND l.description LIKE :q';
            $params['q'] = $this->like($q);
        }
        $result = $this->paginate($sql, $params, $request, 'l.id DESC', 30);
        $result['items'] = array_map(static fn ($r) => $r + ['at' => fa_date($r['created_at'], true)], $result['items']);
        return $this->ok($result);
    }
}
