<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActivityLogger;
use App\Services\HtmlSanitizer;
use App\Services\RedirectService;
use App\Validators\Validator;

/** Generic CRUD for the resources defined in Resources::all(). Routes: /api/admin/r/{resource}[/{id}] */
final class ResourceController extends AdminController
{
    private function def(Request $request, bool $write): array
    {
        $def = Resources::get((string) $request->param('resource'));
        $perm = $write ? $def['perm'] : ($def['view_perm'] ?? $def['perm']);
        foreach (explode('|', $perm) as $p) {
            if (AdminAuth::can($p)) {
                return $def;
            }
        }
        throw new HttpException(403, 'شما مجوز دسترسی به این بخش را ندارید.');
    }

    public function index(Request $request): Response
    {
        $def = $this->def($request, false);
        $base = $def['list'] ?? ('SELECT * FROM ' . Database::ident($def['table']));
        $sql = 'SELECT * FROM (' . $base . ') t WHERE 1 = 1';
        $params = [];
        $q = trim((string) $request->query('q', ''));
        if ($q !== '' && $def['search']) {
            $parts = [];
            foreach ($def['search'] as $i => $col) {
                $parts[] = 't.' . Database::ident($col) . " LIKE :q{$i}";
                $params["q{$i}"] = $this->like($q);
            }
            $sql .= ' AND (' . implode(' OR ', $parts) . ')';
        }
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids', ''))));
        if ($ids) {
            $sql .= ' AND t.id IN ' . Database::in('ids', $ids, $params);
        }
        foreach ($def['filters'] as $i => $col) {
            $value = $request->query($col);
            if ($value !== null && $value !== '') {
                if ($value === 'null') {
                    $sql .= ' AND t.' . Database::ident($col) . ' IS NULL';
                } else {
                    $sql .= ' AND t.' . Database::ident($col) . " = :f{$i}";
                    $params["f{$i}"] = $value;
                }
            }
        }
        $sortable = [];
        foreach ($def['sort'] as $col) {
            $sortable[$col] = 't.' . Database::ident($col);
        }
        $result = $this->paginate($sql, $params, $request, $this->order($request, $sortable, $this->qualify($def['default_sort'])));
        $result['items'] = array_map(fn ($r) => $this->present($def, $r), $result['items']);
        return $this->ok($result);
    }

    private function qualify(string $order): string
    {
        // lower-case column names only, so SQL keywords such as "IS NULL DESC" are left alone
        return (string) preg_replace('/(?<![\w.])([a-z_]+)\s+(ASC|DESC)\b/', 't.$1 $2', $order);
    }

    public function show(Request $request): Response
    {
        $def = $this->def($request, false);
        $row = $this->find($def, $this->intParam($request, 'id'));
        $row = $this->present($def, $row);
        foreach ($def['relations'] as $field => [$table, $own, $other]) {
            $row[$field] = array_map('intval', array_column(Database::all('SELECT ' . Database::ident($other) . ' AS v FROM ' . Database::ident($table) . ' WHERE ' . Database::ident($own) . ' = :id', ['id' => $row['id']]), 'v'));
        }
        return $this->ok($row);
    }

    public function store(Request $request): Response
    {
        $def = $this->def($request, true);
        if ($def['readonly']) {
            throw new HttpException(405, 'ایجاد مورد جدید در این بخش امکان‌پذیر نیست.');
        }
        $data = $this->prepare($def, $request, null);
        [$data, $relations] = $this->splitRelations($def, $data);
        $id = Database::transaction(function () use ($def, $data, $relations) {
            $id = Database::insert($def['table'], $data);
            $this->writeRelations($def, $id, $relations);
            return $id;
        });
        ActivityLogger::admin('create', $def['key'], $id, 'ایجاد ' . $def['label'] . ': ' . $this->title($data), $data);
        return $this->ok(['id' => $id], $def['label'] . ' ایجاد شد.', 201);
    }

    public function update(Request $request): Response
    {
        $def = $this->def($request, true);
        $row = $this->find($def, $this->intParam($request, 'id'));
        $data = $this->prepare($def, $request, $row);
        [$data, $relations] = $this->splitRelations($def, $data);
        Database::transaction(function () use ($def, $row, $data, $relations) {
            Database::update($def['table'], $data, 'id = :id', ['id' => $row['id']]);
            $this->writeRelations($def, (int) $row['id'], $relations);
            if ($def['slug'] && $def['slug'][1] && isset($data['slug']) && $data['slug'] !== $row['slug']) {
                RedirectService::slugChanged($def['slug'][1], (string) $row['slug'], (string) $data['slug']);
            }
        });
        ActivityLogger::admin('update', $def['key'], (int) $row['id'], 'ویرایش ' . $def['label'] . ': ' . $this->title($data + $row), ActivityLogger::diff($row, $data));
        return $this->ok(null, $def['label'] . ' ذخیره شد.');
    }

    public function destroy(Request $request): Response
    {
        $def = $this->def($request, true);
        if ($def['key'] === 'sitemap') {
            throw new HttpException(405, 'این مورد قابل حذف نیست.');
        }
        $row = $this->find($def, $this->intParam($request, 'id'));
        if ($def['before_delete']) {
            ($def['before_delete'])($row);
        }
        Database::query('DELETE FROM ' . Database::ident($def['table']) . ' WHERE id = :id', ['id' => $row['id']]);
        ActivityLogger::admin('delete', $def['key'], (int) $row['id'], 'حذف ' . $def['label'] . ': ' . $this->title($row), $row);
        return $this->ok(null, $def['label'] . ' حذف شد.');
    }

    private function find(array $def, int $id): array
    {
        $row = Database::one('SELECT * FROM ' . Database::ident($def['table']) . ' WHERE id = :id', ['id' => $id]);
        if (!$row) {
            throw new HttpException(404, $def['label'] . ' یافت نشد.');
        }
        return $row;
    }

    /** Validates (partial updates allowed with ?partial=1), sanitises HTML, generates unique slugs. */
    private function prepare(array $def, Request $request, ?array $row): array
    {
        $input = $request->all();
        $rules = $def['fields'];
        if ($row && $request->query('partial') === '1') {
            $rules = array_intersect_key($rules, $input);
        }
        $data = Validator::validate($input, $rules);
        foreach ($def['html'] as $col) {
            if (array_key_exists($col, $data)) {
                $data[$col] = HtmlSanitizer::clean($data[$col]);
            }
        }
        foreach (['is_active', 'is_enabled'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $data[$flag] = $data[$flag] ? 1 : 0;
            }
        }
        if (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        }
        if ($def['slug'] && array_key_exists($def['slug'][0], $data) || ($def['slug'] && array_key_exists('slug', $data))) {
            $source = $data['slug'] ?? '';
            if ($source === '' || $source === null) {
                $source = slugify((string) ($data[$def['slug'][0]] ?? ($row[$def['slug'][0]] ?? '')));
            }
            $data['slug'] = $this->uniqueSlug($def['table'], (string) $source, $row ? (int) $row['id'] : null);
        }
        if ($def['before_save']) {
            $data = ($def['before_save'])($data, $row);
        }
        $allowed = array_merge(array_keys($def['fields']), $def['extra_fields'] ?? [], $def['slug'] ? ['slug'] : []);
        return array_intersect_key($data, array_flip($allowed));
    }

    private function uniqueSlug(string $table, string $slug, ?int $ignoreId): string
    {
        $slug = $slug !== '' ? $slug : 'item';
        $candidate = $slug;
        for ($i = 2; $i < 100; $i++) {
            $exists = Database::value('SELECT 1 FROM ' . Database::ident($table) . ' WHERE slug = :s' . ($ignoreId ? ' AND id <> :id' : ''),
                $ignoreId ? ['s' => $candidate, 'id' => $ignoreId] : ['s' => $candidate]);
            if (!$exists) {
                return $candidate;
            }
            $candidate = $slug . '-' . $i;
        }
        throw new HttpException(422, 'اسلاگ تکراری است.', ['slug' => 'این اسلاگ قبلاً استفاده شده است.']);
    }

    private function splitRelations(array $def, array $data): array
    {
        $relations = [];
        foreach ($def['relations'] as $field => $_) {
            if (array_key_exists($field, $data)) {
                $relations[$field] = array_values(array_unique(array_map('intval', (array) $data[$field])));
                unset($data[$field]);
            }
        }
        return [$data, $relations];
    }

    private function writeRelations(array $def, int $id, array $relations): void
    {
        foreach ($relations as $field => $ids) {
            [$table, $own, $other] = $def['relations'][$field];
            Database::query('DELETE FROM ' . Database::ident($table) . ' WHERE ' . Database::ident($own) . ' = :id', ['id' => $id]);
            foreach ($ids as $value) {
                Database::query('INSERT IGNORE INTO ' . Database::ident($table) . ' (' . Database::ident($own) . ', ' . Database::ident($other) . ') VALUES (:a, :b)', ['a' => $id, 'b' => $value]);
            }
        }
    }

    private function present(array $def, array $row): array
    {
        foreach ($def['dates'] as $col) {
            if (array_key_exists($col, $row)) {
                $row[$col . '_local'] = Validator::toLocalInput($row[$col]);
                $row[$col . '_fa'] = fa_date($row[$col], true);
            }
        }
        if (isset($row['created_at'])) {
            $row['created_at_fa'] = fa_date($row['created_at'], true);
        }
        return $row;
    }

    private function title(array $data): string
    {
        return (string) ($data['name'] ?? $data['title'] ?? $data['code'] ?? $data['source_path'] ?? $data['question'] ?? $data['email'] ?? $data['subject'] ?? ('#' . ($data['id'] ?? '')));
    }
}
