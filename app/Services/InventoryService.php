<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;

/** Every stock change goes through here so inventory_movements is a complete ledger. */
final class InventoryService
{
    public static function adjust(int $productId, int $variantId, int $change, string $reason, ?string $refType, ?int $refId, ?string $note, ?int $adminId): int
    {
        Database::query('INSERT IGNORE INTO inventory (product_id, variant_id, quantity) VALUES (:p, :v, 0)', ['p' => $productId, 'v' => $variantId]);
        $current = (int) Database::value('SELECT quantity FROM inventory WHERE product_id = :p AND variant_id = :v FOR UPDATE', ['p' => $productId, 'v' => $variantId]);
        $after = $current + $change;
        if ($after < 0) {
            throw new HttpException(409, 'موجودی کافی نیست.');
        }
        Database::query('UPDATE inventory SET quantity = :q WHERE product_id = :p AND variant_id = :v', ['q' => $after, 'p' => $productId, 'v' => $variantId]);
        Database::insert('inventory_movements', [
            'product_id' => $productId, 'variant_id' => $variantId, 'change_qty' => $change, 'quantity_after' => $after,
            'reason' => $reason, 'reference_type' => $refType, 'reference_id' => $refId, 'note' => $note, 'admin_user_id' => $adminId,
        ]);
        if ($change < 0) {
            $threshold = (int) Database::value('SELECT low_stock_threshold FROM inventory WHERE product_id = :p AND variant_id = :v', ['p' => $productId, 'v' => $variantId]);
            if ($after <= $threshold && $current > $threshold) {
                $name = (string) Database::value('SELECT name FROM products WHERE id = :id', ['id' => $productId]);
                NotificationService::admins('stock.low', 'موجودی کم: ' . $name, 'موجودی فعلی: ' . fa_number($after), '#/products/' . $productId);
            }
        }
        return $after;
    }

    /** Sets an absolute quantity (admin edit) and logs the difference. */
    public static function set(int $productId, int $variantId, int $quantity, ?int $adminId, ?string $note = null): void
    {
        Database::query('INSERT IGNORE INTO inventory (product_id, variant_id, quantity) VALUES (:p, :v, 0)', ['p' => $productId, 'v' => $variantId]);
        $current = (int) Database::value('SELECT quantity FROM inventory WHERE product_id = :p AND variant_id = :v', ['p' => $productId, 'v' => $variantId]);
        if ($current !== $quantity) {
            self::adjust($productId, $variantId, $quantity - $current, 'manual', null, null, $note ?? 'ویرایش دستی موجودی', $adminId);
        }
    }
}
