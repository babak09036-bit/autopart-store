<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;
use App\Services\NotificationService;
use App\Services\ReviewService;
use App\Services\VehicleService;
use App\Validators\Validator;

final class CatalogController extends Controller
{
    /** GET /api/products — filters: q, cat, brand, min, max, vehicle, type, instock, discount, amazing, best, expert, new, ids, sort, page, per_page */
    public function index(Request $request): Response
    {
        $f = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'cat' => $this->listQuery($request, 'cat'),
            'brand' => $this->listQuery($request, 'brand'),
            'ids' => array_map('intval', array_filter($this->listQuery($request, 'ids'), 'ctype_digit')),
            'min' => is_numeric($request->query('min')) ? (float) $request->query('min') : null,
            'max' => is_numeric($request->query('max')) ? (float) $request->query('max') : null,
            'vehicle' => (string) $request->query('vehicle', ''),
            'year' => ctype_digit(to_en_digits((string) $request->query('year', ''))) ? (int) to_en_digits((string) $request->query('year')) : null,
            'type' => mb_substr((string) $request->query('type', ''), 0, 80),
            'sort' => (string) $request->query('sort', ''),
            'page' => (int) $request->query('page', 1),
            'per_page' => (int) $request->query('per_page', 24),
        ];
        foreach (['instock', 'discount', 'amazing', 'best', 'expert', 'new'] as $flag) {
            $f[$flag] = $request->query($flag) === '1';
        }
        if ($f['ids']) {
            $f['keep_order'] = true;
        }
        return $this->ok(CatalogService::search($f, $request->query('facets') === '1'));
    }

    public function show(Request $request): Response
    {
        $row = CatalogService::find($this->intParam($request, 'id'));
        if (!$row) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        return $this->ok(CatalogService::details($row));
    }

    public function showBySlug(Request $request): Response
    {
        $row = CatalogService::find((string) $request->param('slug'));
        if (!$row) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        return $this->ok(CatalogService::details($row));
    }

    public function suggest(Request $request): Response
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        if (mb_strlen($q) < 2) {
            return $this->ok(['items' => [], 'total' => 0]);
        }
        $result = CatalogService::search(['q' => $q, 'per_page' => 6]);
        return $this->ok(['items' => $result['items'], 'total' => $result['pagination']['total']]);
    }

    public function categories(): Response
    {
        $rows = Database::all("SELECT c.id, c.parent_id, c.name, c.short_name, c.slug, c.description, c.image_path,
            (SELECT COUNT(DISTINCT pc.product_id) FROM product_categories pc JOIN products p ON p.id = pc.product_id AND p.status = 'published'
              WHERE pc.category_id = c.id OR pc.category_id IN (SELECT ch.id FROM categories ch WHERE ch.parent_id = c.id)) AS product_count
            FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order, c.name");
        return $this->ok(array_map(static fn ($c) => [
            'id' => (int) $c['id'], 'parent_id' => $c['parent_id'] ? (int) $c['parent_id'] : null, 'name' => $c['name'],
            'short_name' => $c['short_name'] ?: $c['name'], 'slug' => $c['slug'], 'url' => 'categories/' . $c['slug'],
            'description' => $c['description'], 'image' => $c['image_path'], 'product_count' => (int) $c['product_count'],
        ], $rows));
    }

    public function brands(): Response
    {
        return $this->ok(Database::all("SELECT b.name, b.slug, b.logo_path AS logo, b.description,
            (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.status = 'published') AS product_count
            FROM brands b WHERE b.is_active = 1 ORDER BY b.sort_order, b.name"));
    }

    public function vehicles(): Response
    {
        return $this->ok(VehicleService::tree());
    }

    /** GET /api/products/{id}/fitment?vehicle={model slug}&year={solar year} — does this product fit that vehicle? */
    public function fitment(Request $request): Response
    {
        $row = CatalogService::find($this->intParam($request, 'id'));
        if (!$row) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        $data = $this->validate(['vehicle' => $request->query('vehicle'), 'year' => $request->query('year')],
            ['vehicle' => 'required|string|maxlen:120', 'year' => 'nullable|int|min:1300|max:1500']);
        $result = CatalogService::fitment(CatalogService::dto($row), $data['vehicle'], isset($data['year']) ? (int) $data['year'] : null);
        if ($result['status'] === 'invalid') {
            throw new HttpException(422, 'خودروی انتخاب‌شده یافت نشد.', ['vehicle' => 'خودرو را دوباره انتخاب کنید.']);
        }
        return $this->ok($result);
    }

    /* ---------------- Reviews ---------------- */

    public function reviews(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        if (!CatalogService::find($id)) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        return $this->ok(ReviewService::listForProduct($id, $request->query, Auth::id()));
    }

    public function storeReview(Request $request): Response
    {
        $result = ReviewService::create($this->intParam($request, 'id'), (int) Auth::id(), $request->all(), $request->files, $request->ip());
        return $this->ok($result, 'نظر شما ثبت شد و پس از بررسی منتشر می‌شود.', 201);
    }

    /* ---------------- Product questions ---------------- */

    public function questions(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        return $this->ok(array_map(static fn ($q) => [
            'question' => $q['question'], 'answer' => $q['answer'], 'at' => fa_date($q['created_at']),
        ], Database::all("SELECT question, answer, created_at FROM product_questions WHERE product_id = :p AND status = 'approved' ORDER BY id DESC LIMIT 20", ['p' => $id])));
    }

    public function storeQuestion(Request $request): Response
    {
        $id = $this->intParam($request, 'id');
        if (!CatalogService::find($id)) {
            throw new HttpException(404, 'محصول یافت نشد.');
        }
        RateLimiter::enforce('question:' . Auth::id(), 5, 3600);
        $data = Validator::validate($request->all(), ['question' => 'required|string|minlen:10|maxlen:1000']);
        Database::insert('product_questions', ['product_id' => $id, 'user_id' => Auth::id(), 'question' => $data['question']]);
        NotificationService::admins('question.new', 'پرسش جدید درباره محصول', mb_substr($data['question'], 0, 120), '#/questions');
        return $this->ok(null, 'پرسش شما ثبت شد و پس از پاسخ کارشناس نمایش داده می‌شود.', 201);
    }
}
