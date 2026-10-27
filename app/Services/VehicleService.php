<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class VehicleService
{
    /** brand → models tree for the vehicle selector. */
    public static function tree(): array
    {
        $brands = Database::all('SELECT id, name, slug FROM vehicle_brands ORDER BY sort_order, name');
        $models = Database::all('SELECT vehicle_brand_id, name, slug, year_from, year_to FROM vehicle_models ORDER BY sort_order, name');
        return array_map(static function ($b) use ($models) {
            return [
                'slug' => $b['slug'],
                'name' => $b['name'],
                'models' => array_values(array_map(static fn ($m) => [
                    'slug' => $m['slug'], 'name' => $m['name'], 'years' => [(int) $m['year_from'], (int) $m['year_to']],
                ], array_filter($models, static fn ($m) => (int) $m['vehicle_brand_id'] === (int) $b['id']))),
            ];
        }, $brands);
    }
}
