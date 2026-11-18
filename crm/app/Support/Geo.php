<?php

namespace App\Support;

/** Distances between two points on the map, and a Google Maps link to look at them. */
class Geo
{
    /** Straight-line distance in metres. */
    public static function metres(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public static function mapLink(float $lat, float $lng): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.round($lat, 6).','.round($lng, 6);
    }

    /** @param array<int, array{0: float, 1: float}> $points  Route through up to 10 points, in order. */
    public static function routeLink(array $points): ?string
    {
        $points = array_slice($points, 0, 10);
        if (count($points) < 2) {
            return null;
        }

        return 'https://www.google.com/maps/dir/'.implode('/', array_map(fn ($p) => round($p[0], 6).','.round($p[1], 6), $points));
    }

    /** @param array<int, array{0: float, 1: float}> $points */
    public static function pathKm(array $points): float
    {
        $m = 0;
        for ($i = 1; $i < count($points); $i++) {
            $m += self::metres($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
        }

        return round($m / 1000, 1);
    }
}
