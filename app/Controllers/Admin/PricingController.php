<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\PricingService;

/** Bulk price changes (permission pricing.bulk): preview, apply, history and undo. */
final class PricingController extends AdminController
{
    private const RULES = [
        'category_ids' => 'nullable|array', 'brand_ids' => 'nullable|array', 'vehicle_model_ids' => 'nullable|array',
        'include_universal' => 'nullable|bool', 'status' => 'required|in:published,all',
        'direction' => 'required|in:increase,decrease', 'type' => 'required|in:percent,fixed', 'value' => 'required|numeric|min:0.01',
        'target' => 'required|in:both,price,sale', 'rounding' => 'required|in:0,100,1000,10000', 'variants' => 'nullable|bool',
        'confirm_all' => 'nullable|bool',
    ];

    /** @return array{0: array, 1: array} scope, rule */
    private function input(Request $request): array
    {
        $d = $this->validate($request->all(), self::RULES);
        if ($d['type'] === 'percent' && ($d['value'] > 100 || ($d['direction'] === 'decrease' && $d['value'] >= 100))) {
            throw new HttpException(422, 'درصد تغییر باید کمتر از ۱۰۰ باشد.', ['value' => 'حداکثر ۱۰۰٪ (برای کاهش کمتر از ۱۰۰٪).']);
        }
        $scope = ['category_ids' => $d['category_ids'] ?? [], 'brand_ids' => $d['brand_ids'] ?? [], 'vehicle_model_ids' => $d['vehicle_model_ids'] ?? [],
            'include_universal' => !empty($d['include_universal']), 'status' => $d['status']];
        $rule = ['direction' => $d['direction'], 'type' => $d['type'], 'value' => (float) $d['value'], 'target' => $d['target'],
            'rounding' => (int) $d['rounding'], 'variants' => !empty($d['variants'])];
        if (!$scope['category_ids'] && !$scope['brand_ids'] && !$scope['vehicle_model_ids'] && empty($d['confirm_all'])) {
            throw new HttpException(422, 'هیچ دسته، برند یا خودرویی انتخاب نشده است؛ برای تغییر قیمت همه محصولات گزینه «همه محصولات» را تأیید کنید.', ['confirm_all' => 'تأیید لازم است.']);
        }
        return [$scope, $rule];
    }

    public function preview(Request $request): Response
    {
        [$scope, $rule] = $this->input($request);
        $plan = PricingService::plan(PricingService::productIds($scope), $rule);
        return $this->ok([
            'description' => PricingService::describe($scope, $rule),
            'products' => $plan['products'], 'changes' => $plan['changes'], 'skipped' => $plan['skipped'], 'sale_cleared' => $plan['sale_cleared'],
            'rows' => array_slice($plan['rows'], 0, 300), 'truncated' => count($plan['rows']) > 300,
        ]);
    }

    public function apply(Request $request): Response
    {
        [$scope, $rule] = $this->input($request);
        $description = PricingService::describe($scope, $rule);
        $result = PricingService::apply($scope, $rule, AdminAuth::id(), $description);
        ActivityLogger::admin('price.bulk', 'price_batch', $result['batch_id'], 'تغییر گروهی قیمت: ' . $description,
            ['changes' => $result['changes'], 'products' => $result['products'], 'skipped' => $result['skipped'], 'sale_cleared' => $result['sale_cleared']]);
        return $this->ok($result, 'قیمت ' . fa_number($result['products']) . ' محصول تغییر کرد.');
    }

    public function batches(Request $request): Response
    {
        $result = $this->paginate('SELECT b.id, b.description, b.products_count, b.items_count, b.created_at, b.reverted_at, a.full_name AS admin_name, r.full_name AS reverted_by_name
            FROM price_change_batches b LEFT JOIN admin_users a ON a.id = b.admin_user_id LEFT JOIN admin_users r ON r.id = b.reverted_by', [], $request, 'b.id DESC', 10);
        $result['items'] = array_map(static fn ($b) => $b + ['at' => fa_date($b['created_at'], true), 'reverted_at_fa' => $b['reverted_at'] ? fa_date($b['reverted_at'], true) : null], $result['items']);
        return $this->ok($result);
    }

    public function batch(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        if (!Database::value('SELECT 1 FROM price_change_batches WHERE id = :id', ['id' => $id])) {
            throw new HttpException(404, 'این تغییر گروهی یافت نشد.');
        }
        return $this->ok(Database::all('SELECT i.*, p.name, v.name AS variant_name FROM price_change_items i JOIN products p ON p.id = i.product_id
            LEFT JOIN product_variants v ON v.id = i.variant_id WHERE i.batch_id = :id ORDER BY i.id LIMIT 500', ['id' => $id]));
    }

    public function revert(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        $result = PricingService::revert($id, AdminAuth::id());
        ActivityLogger::admin('price.bulk_revert', 'price_batch', $id, 'بازگردانی تغییر گروهی قیمت: ' . $result['description'], ['restored' => $result['restored'], 'kept' => $result['kept']]);
        $msg = 'قیمت ' . fa_number($result['restored']) . ' مورد به قبل برگشت.' . ($result['kept'] ? ' ' . fa_number($result['kept']) . ' مورد که بعداً دستی تغییر کرده بود، دست‌نخورده ماند.' : '');
        return $this->ok($result, $msg);
    }
}
