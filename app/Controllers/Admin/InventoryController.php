<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\InventoryService;

/**
 * Stock screen: one row per sellable item (the product itself, or each variant when it has variants).
 * Every change goes through InventoryService, so inventory_movements stays a complete ledger.
 */
final class InventoryController extends AdminController
{
    /** Sellable rows only: variant rows of products with variants, otherwise the product row. */
    private const BASE = "SELECT i.id, i.product_id, i.variant_id, i.quantity, i.low_stock_threshold, i.updated_at,
            p.name, p.slug, p.sku, p.status, p.brand_id, COALESCE(v.price, p.price) AS price, v.name AS variant_name, v.sku AS variant_sku, b.name AS brand_name,
            (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order LIMIT 1) AS image,
            (SELECT c.name FROM product_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.product_id = p.id ORDER BY pc.is_primary DESC LIMIT 1) AS category_name
        FROM inventory i JOIN products p ON p.id = i.product_id LEFT JOIN product_variants v ON v.id = i.variant_id LEFT JOIN brands b ON b.id = p.brand_id
        WHERE p.status <> 'archived' AND ((i.variant_id > 0 AND v.id IS NOT NULL) OR (i.variant_id = 0 AND NOT EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id)))";

    public function index(Request $request): Response
    {
        [$sql, $params] = $this->filtered($request);
        $wrapped = 'SELECT * FROM (' . $sql . ') t';
        $result = $this->paginate($wrapped, $params, $request, $this->order($request, ['quantity' => 't.quantity', 'name' => 't.name', 'updated' => 't.updated_at'], 't.quantity ASC, t.name ASC'), 30);
        $summary = Database::one('SELECT COUNT(*) AS items, COALESCE(SUM(t.quantity), 0) AS units, COALESCE(SUM(t.quantity * t.price), 0) AS value,
                SUM(t.quantity = 0) AS out_count, SUM(t.quantity > 0 AND t.quantity <= t.low_stock_threshold) AS low_count
            FROM (' . $sql . ') t', $params);
        $result['summary'] = array_map(static fn ($v) => (float) $v, $summary ?: []);
        return $this->ok($result);
    }

    /** @return array{0: string, 1: array} */
    private function filtered(Request $request): array
    {
        $sql = self::BASE;
        $params = [];
        if ($q = trim(to_en_digits((string) $request->query('q', '')))) {
            $sql .= ' AND (p.name LIKE :q1 OR p.sku LIKE :q2 OR v.sku LIKE :q3 OR v.name LIKE :q4)';
            $params += ['q1' => $this->like($q), 'q2' => $this->like($q), 'q3' => $this->like($q), 'q4' => $this->like($q)];
        }
        if (ctype_digit((string) $request->query('category_id', ''))) {
            $sql .= ' AND EXISTS (SELECT 1 FROM product_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.product_id = p.id AND (c.id = :cat OR c.parent_id = :cat2))';
            $params['cat'] = $params['cat2'] = (int) $request->query('category_id');
        }
        if (ctype_digit((string) $request->query('brand_id', ''))) {
            $sql .= ' AND p.brand_id = :brand';
            $params['brand'] = (int) $request->query('brand_id');
        }
        if (ctype_digit((string) $request->query('vehicle_model_id', ''))) {
            $sql .= ' AND EXISTS (SELECT 1 FROM product_vehicle_compat pvc WHERE pvc.product_id = p.id AND pvc.vehicle_model_id = :vm)';
            $params['vm'] = (int) $request->query('vehicle_model_id');
        }
        $sql .= match ((string) $request->query('stock', '')) {
            'out' => ' AND i.quantity = 0',
            'low' => ' AND i.quantity > 0 AND i.quantity <= i.low_stock_threshold',
            'ok' => ' AND i.quantity > i.low_stock_threshold',
            default => '',
        };
        return [$sql, $params];
    }

    /** PUT /admin/inventory/{id}: set an absolute quantity and/or the low-stock threshold. */
    public function update(Request $request): Response
    {
        $row = $this->row($this->intParam($request, 'id'));
        $d = $this->validate($request->all(), ['quantity' => 'nullable|int|min:0|max:1000000', 'low_stock_threshold' => 'nullable|int|min:0|max:100000', 'note' => 'nullable|string|maxlen:255']);
        Database::transaction(static function () use ($row, $d) {
            if (isset($d['quantity'])) {
                InventoryService::set((int) $row['product_id'], (int) $row['variant_id'], (int) $d['quantity'], AdminAuth::id(), $d['note'] ?? 'ویرایش از صفحه انبار');
            }
            if (isset($d['low_stock_threshold'])) {
                Database::update('inventory', ['low_stock_threshold' => (int) $d['low_stock_threshold']], 'id = :id', ['id' => $row['id']]);
            }
        });
        $after = $this->row((int) $row['id']);
        if ((int) $after['quantity'] !== (int) $row['quantity'] || (int) $after['low_stock_threshold'] !== (int) $row['low_stock_threshold']) {
            ActivityLogger::admin('stock.change', 'product', (int) $row['product_id'], 'ویرایش موجودی: ' . $row['name'] . ($row['variant_name'] ? ' — ' . $row['variant_name'] : ''),
                ActivityLogger::diff(['quantity' => $row['quantity'], 'low_stock_threshold' => $row['low_stock_threshold']], ['quantity' => $after['quantity'], 'low_stock_threshold' => $after['low_stock_threshold']]));
        }
        return $this->ok(['quantity' => (int) $after['quantity'], 'low_stock_threshold' => (int) $after['low_stock_threshold']], 'موجودی ذخیره شد.');
    }

    /** POST /admin/inventory/bulk: add / subtract / set for many rows; all or nothing. */
    public function bulk(Request $request): Response
    {
        $d = $this->validate($request->all(), ['ids' => 'required|array|max:500', 'mode' => 'required|in:add,subtract,set,threshold', 'value' => 'required|int|min:0|max:1000000',
            'note' => 'nullable|string|maxlen:255']);
        $ids = array_values(array_unique(array_filter(array_map('intval', $d['ids']))));
        $value = (int) $d['value'];
        $note = ($d['note'] ?? '') ?: 'تغییر گروهی از صفحه انبار';
        $count = Database::transaction(function () use ($ids, $d, $value, $note) {
            $done = 0;
            foreach ($ids as $id) {
                $row = $this->row($id);
                $pid = (int) $row['product_id'];
                $vid = (int) $row['variant_id'];
                match ($d['mode']) {
                    'add' => InventoryService::adjust($pid, $vid, $value, 'adjustment', null, null, $note, AdminAuth::id()),
                    'subtract' => (int) $row['quantity'] >= $value
                        ? InventoryService::adjust($pid, $vid, -$value, 'adjustment', null, null, $note, AdminAuth::id())
                        : throw new HttpException(409, 'موجودی «' . $row['name'] . ($row['variant_name'] ? ' — ' . $row['variant_name'] : '') . '» (' . fa_number((int) $row['quantity']) . ') کمتر از مقدار کسر است؛ هیچ تغییری ذخیره نشد.'),
                    'set' => InventoryService::set($pid, $vid, $value, AdminAuth::id(), $note),
                    'threshold' => Database::update('inventory', ['low_stock_threshold' => $value], 'id = :id', ['id' => $id]),
                };
                $done++;
            }
            return $done;
        });
        $label = ['add' => 'افزایش', 'subtract' => 'کاهش', 'set' => 'تنظیم', 'threshold' => 'تغییر حد هشدار'][$d['mode']];
        ActivityLogger::admin('stock.bulk', 'inventory', null, $label . ' گروهی موجودی برای ' . fa_number($count) . ' کالا', ['mode' => $d['mode'], 'value' => $value, 'ids' => $ids, 'note' => $note]);
        return $this->ok(['count' => $count], 'موجودی ' . fa_number($count) . ' کالا به‌روزرسانی شد.');
    }

    /** GET /admin/inventory/movements: the stock ledger. */
    public function movements(Request $request): Response
    {
        $sql = 'SELECT m.id, m.product_id, m.variant_id, m.change_qty, m.quantity_after, m.reason, m.reference_type, m.reference_id, m.note, m.created_at,
                p.name, v.name AS variant_name, a.full_name AS admin_name, o.order_number
            FROM inventory_movements m JOIN products p ON p.id = m.product_id LEFT JOIN product_variants v ON v.id = m.variant_id
            LEFT JOIN admin_users a ON a.id = m.admin_user_id LEFT JOIN orders o ON m.reference_type = \'order\' AND o.id = m.reference_id WHERE 1 = 1';
        $params = [];
        if (ctype_digit((string) $request->query('product_id', ''))) {
            $sql .= ' AND m.product_id = :p';
            $params['p'] = (int) $request->query('product_id');
        }
        if (in_array($r = (string) $request->query('reason', ''), ['initial', 'manual', 'order', 'order_cancel', 'return', 'adjustment'], true)) {
            $sql .= ' AND m.reason = :r';
            $params['r'] = $r;
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $sql .= ' AND (p.name LIKE :q1 OR m.note LIKE :q2)';
            $params += ['q1' => $this->like($q), 'q2' => $this->like($q)];
        }
        $result = $this->paginate($sql, $params, $request, 'm.id DESC', 30);
        $result['items'] = array_map(static fn ($m) => $m + ['at' => fa_date($m['created_at'], true)], $result['items']);
        return $this->ok($result);
    }

    private function row(int $id): array
    {
        $row = Database::one('SELECT i.*, p.name, v.name AS variant_name FROM inventory i JOIN products p ON p.id = i.product_id LEFT JOIN product_variants v ON v.id = i.variant_id WHERE i.id = :id', ['id' => $id]);
        if (!$row) {
            throw new HttpException(404, 'ردیف موجودی یافت نشد.');
        }
        return $row;
    }
}
