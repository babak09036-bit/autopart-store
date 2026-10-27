<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Validators\Validator;

abstract class Controller
{
    protected function validate(array $data, array $rules): array
    {
        return Validator::validate($data, $rules);
    }

    protected function ok(mixed $data = null, string $message = 'عملیات با موفقیت انجام شد', int $status = 200): Response
    {
        return Response::ok($data, $message, $status);
    }

    protected function intParam(Request $request, string $key): int
    {
        $value = $request->param($key);
        if ($value === null || !ctype_digit($value)) {
            throw new HttpException(404, 'مورد درخواستی یافت نشد.');
        }
        return (int) $value;
    }

    /** Comma list or array query value → array of strings. */
    protected function listQuery(Request $request, string $key): array
    {
        $raw = $request->query[$key] ?? '';
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);
        return array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $values), static fn ($v) => $v !== '' && mb_strlen($v) <= 120));
    }
}
