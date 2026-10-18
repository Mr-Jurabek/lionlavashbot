<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Yetkazish hududi: oshxonadan havo chizig'i bo'yicha delivery_radius_km (3–5 km) ichida.
 */
final class DeliveryZone
{
    private const EARTH_RADIUS_KM = 6371.0;
    public const RADIUS_MIN = 3.0;
    public const RADIUS_MAX = 5.0;

    /** @return array{lat: float, lng: float}|null null — oshxona lokatsiyasi hali kiritilmagan */
    public static function restaurant(): ?array
    {
        $lat = trim(Setting::get('restaurant_lat'));
        $lng = trim(Setting::get('restaurant_lng'));
        if ($lat === '' || $lng === '') {
            return null;
        }
        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    public static function radiusKm(): float
    {
        $radius = (float) Setting::get('delivery_radius_km', '5');
        return max(self::RADIUS_MIN, min(self::RADIUS_MAX, $radius));
    }

    /** Haversine formulasi bo'yicha masofa, km. */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Manzil hududga kirmasa yoki oshxona sozlanmagan bo'lsa ServiceException. */
    public static function check(float $lat, float $lng): void
    {
        $restaurant = self::restaurant();
        if ($restaurant === null) {
            throw new ServiceException('restaurant_not_configured');
        }
        if (self::distanceKm($restaurant['lat'], $restaurant['lng'], $lat, $lng) > self::radiusKm()) {
            throw new ServiceException('out_of_radius');
        }
    }
}
