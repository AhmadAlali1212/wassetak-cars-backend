<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class CarListingFilters
{
    /**
     * قواعد الفلترة المشتركة بين السيارات وإعلانات البيع والتأجير والمزادات.
     */
    public static function rules(): array
    {
        $maxYear = date('Y') + 1;

        return [
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'in:new,used'],
            'year_from' => ['nullable', 'integer', 'min:1900', 'max:' . $maxYear],
            'year_to' => ['nullable', 'integer', 'min:1900', 'max:' . $maxYear],
            'location' => ['nullable', 'string', 'max:255'],
            'user_lat' => ['nullable', 'required_with:user_lng,radius', 'numeric', 'between:-90,90'],
            'user_lng' => ['nullable', 'required_with:user_lat,radius', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'required_with:user_lat,user_lng', 'numeric', 'min:0.1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * التحقق من ترتيب سنوات البحث.
     */
    public static function yearRangeErrors(Request $request): array
    {
        if (
            $request->filled('year_from')
            && $request->filled('year_to')
            && (int) $request->year_to < (int) $request->year_from
        ) {
            return [
                'year_to' => ['يجب أن تكون سنة النهاية أكبر من أو تساوي سنة البداية.'],
            ];
        }

        return [];
    }

    /**
     * تطبيق الفلاتر على استعلام Car مباشر.
     */
    public static function applyToCarQuery(
        Builder $query,
        Request $request,
        bool $includeLocation = true
    ): Builder {
        if ($request->filled('make')) {
            $query->where('make', 'like', '%' . trim((string) $request->make) . '%');
        }

        if ($request->filled('model')) {
            $query->where('model', 'like', '%' . trim((string) $request->model) . '%');
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->filled('year_from')) {
            $query->where('year', '>=', (int) $request->year_from);
        }

        if ($request->filled('year_to')) {
            $query->where('year', '<=', (int) $request->year_to);
        }

        if ($includeLocation && $request->filled('location')) {
            $query->where('location', 'like', '%' . trim((string) $request->location) . '%');
        }

        if ($request->filled(['user_lat', 'user_lng', 'radius'])) {
            $userLat = (float) $request->user_lat;
            $userLng = (float) $request->user_lng;
            $radius = (float) $request->radius;

            $query->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
                    [$userLat, $userLng, $userLat, $radius]
                );
        }

        return $query;
    }

    /**
     * تطبيق نفس الفلاتر على إعلان أو مزاد مرتبط بعلاقة car.
     */
    public static function applyThroughCarRelation(
        Builder $query,
        Request $request,
        bool $includeLocation = true,
        string $relation = 'car'
    ): Builder {
        $keys = [
            'make',
            'model',
            'condition',
            'year_from',
            'year_to',
            'user_lat',
            'user_lng',
            'radius',
        ];

        if ($includeLocation) {
            $keys[] = 'location';
        }

        $hasFilter = collect($keys)->contains(
            fn (string $key): bool => $request->filled($key)
        );

        if (!$hasFilter) {
            return $query;
        }

        return $query->whereHas($relation, function (Builder $carQuery) use ($request, $includeLocation) {
            self::applyToCarQuery($carQuery, $request, $includeLocation);
        });
    }
}
