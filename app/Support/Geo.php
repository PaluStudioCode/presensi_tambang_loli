<?php

namespace App\Support;

class Geo
{
    public static function distanceInMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($lat1);
        $latTo = deg2rad($lat2);
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $angle = 2 * asin(sqrt(
            sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2
        ));

        return $angle * $earthRadius;
    }

    public static function formatLocation(float $latitude, float $longitude): string
    {
        return number_format($latitude, 6, '.', '').','.number_format($longitude, 6, '.', '');
    }
}
