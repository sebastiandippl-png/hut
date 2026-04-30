<?php

declare(strict_types=1);

namespace Hut;

/**
 * Fetches and caches weather forecast data from Open-Meteo for St. Veit im Pongau.
 *
 * Coordinates: 47.33056 N, 13.15556 E
 * Timezone: Europe/Vienna
 * Forecast: 14 days, metric units (°C, km/h, mm)
 *
 * Icon names are Meteocons identifiers (https://github.com/basmilius/meteocons, MIT).
 */
class WeatherForecast
{
    private const API_URL       = 'https://api.open-meteo.com/v1/forecast';
    private const HISTORICAL_API_URL = 'https://archive-api.open-meteo.com/v1/archive';
    private const LAT           = 47.33056;
    private const LON           = 13.15556;
    private const TIMEZONE      = 'Europe/Vienna';
    private const FORECAST_DAYS = 14;
    private const CACHE_TTL     = 1800; // 30 minutes
    private const HISTORICAL_CACHE_TTL = 604800; // 7 days
    private const MAX_RETRIES   = 3;
    private const ICON_CDN_BASE = 'https://cdn.meteocons.com/3.0.0-next.10/svg/fill/';
    private const TRIP_START_DATE = '2025-09-25';
    private const TRIP_END_DATE   = '2025-10-04';
    private const TRIP_2024_START_DATE = '2024-09-26';
    private const TRIP_2024_END_DATE   = '2024-10-05';
    private const TRIP_2023_START_DATE = '2023-09-30';
    private const TRIP_2023_END_DATE   = '2023-10-07';
    private const TRIP_2022_START_DATE = '2022-10-03';
    private const TRIP_2022_END_DATE   = '2022-10-10';
    private const TRIP_2021_START_DATE = '2021-09-25';
    private const TRIP_2021_END_DATE   = '2021-10-02';

    private static string $cacheFile = '';
    private static string $tripCacheFile = '';
    private static string $trip2024CacheFile = '';
    private static string $trip2023CacheFile = '';
    private static string $trip2022CacheFile = '';
    private static string $trip2021CacheFile = '';

    private static function cacheFile(): string
    {
        if (self::$cacheFile === '') {
            self::$cacheFile = dirname(__DIR__) . '/storage/weather_cache/st_veit_pongau.json';
        }
        return self::$cacheFile;
    }

    private static function tripCacheFile(): string
    {
        if (self::$tripCacheFile === '') {
            self::$tripCacheFile = dirname(__DIR__) . '/storage/weather_cache/st_veit_pongau_trip_2025.json';
        }
        return self::$tripCacheFile;
    }

    private static function trip2024CacheFile(): string
    {
        if (self::$trip2024CacheFile === '') {
            self::$trip2024CacheFile = dirname(__DIR__) . '/storage/weather_cache/st_veit_pongau_trip_2024.json';
        }
        return self::$trip2024CacheFile;
    }

    private static function trip2023CacheFile(): string
    {
        if (self::$trip2023CacheFile === '') {
            self::$trip2023CacheFile = dirname(__DIR__) . '/storage/weather_cache/st_veit_pongau_trip_2023.json';
        }
        return self::$trip2023CacheFile;
    }

    private static function trip2022CacheFile(): string
    {
        if (self::$trip2022CacheFile === '') {
            self::$trip2022CacheFile = dirname(__DIR__) . '/storage/weather_cache/st_veit_pongau_trip_2022.json';
        }
        return self::$trip2022CacheFile;
    }

    private static function trip2021CacheFile(): string
    {
        if (self::$trip2021CacheFile === '') {
            self::$trip2021CacheFile = dirname(__DIR__) . '/storage/weather_cache/st_veit_pongau_trip_2021.json';
        }
        return self::$trip2021CacheFile;
    }

    /**
     * Returns normalized forecast data or null if unavailable.
     *
     * Returned array shape:
     *   'current' => [...Open-Meteo current fields...]
     *   'daily'   => [...Open-Meteo daily arrays...]
     *   'cached_at' => int (Unix timestamp)
     */
    public static function get(): ?array
    {
        $cached = self::readCache();
        if ($cached !== null) {
            return $cached;
        }

        $data = self::fetchFromApi();
        if ($data === null) {
            return null;
        }

        $data['cached_at'] = time();
        self::writeCache($data);
        return $data;
    }

    /**
     * Returns historical weather for the 2025 hut trip date range.
     */
    public static function getTripHistory(): ?array
    {
        $cached = self::readTripCache();
        if ($cached !== null) {
            return $cached;
        }

        $data = self::fetchTripHistoryFromApi();
        if ($data === null) {
            return null;
        }

        $data['cached_at'] = time();
        self::writeTripCache($data);
        return $data;
    }

    /**
     * Returns historical weather for the 2022 hut trip date range.
     */
    public static function getTripHistory2022(): ?array
    {
        $cached = self::readTrip2022Cache();
        if ($cached !== null) {
            return $cached;
        }

        $data = self::fetchTripHistoryForRange(self::TRIP_2022_START_DATE, self::TRIP_2022_END_DATE);
        if ($data === null) {
            return null;
        }

        $data['cached_at'] = time();
        self::writeTrip2022Cache($data);
        return $data;
    }

    /**
     * Returns historical weather for the 2021 hut trip date range.
     */
    public static function getTripHistory2021(): ?array
    {
        $cached = self::readTrip2021Cache();
        if ($cached !== null) {
            return $cached;
        }

        $data = self::fetchTripHistoryForRange(self::TRIP_2021_START_DATE, self::TRIP_2021_END_DATE);
        if ($data === null) {
            return null;
        }

        $data['cached_at'] = time();
        self::writeTrip2021Cache($data);
        return $data;
    }

    /**
     * Returns historical weather for the 2023 hut trip date range.
     */
    public static function getTripHistory2023(): ?array
    {
        $cached = self::readTrip2023Cache();
        if ($cached !== null) {
            return $cached;
        }

        $data = self::fetchTripHistoryForRange(self::TRIP_2023_START_DATE, self::TRIP_2023_END_DATE);
        if ($data === null) {
            return null;
        }

        $data['cached_at'] = time();
        self::writeTrip2023Cache($data);
        return $data;
    }

    /**
     * Returns historical weather for the 2024 hut trip date range.
     */
    public static function getTripHistory2024(): ?array
    {
        $cached = self::readTrip2024Cache();
        if ($cached !== null) {
            return $cached;
        }

        $data = self::fetchTripHistoryForRange(self::TRIP_2024_START_DATE, self::TRIP_2024_END_DATE);
        if ($data === null) {
            return null;
        }

        $data['cached_at'] = time();
        self::writeTrip2024Cache($data);
        return $data;
    }

    // ── Cache helpers ────────────────────────────────────────────────────────

    private static function readCache(): ?array
    {
        $file = self::cacheFile();
        if (!file_exists($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['cached_at'])) {
            return null;
        }

        if (time() - (int) $data['cached_at'] > self::CACHE_TTL) {
            return null;
        }

        return $data;
    }

    private static function writeCache(array $data): void
    {
        $file = self::cacheFile();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private static function readTripCache(): ?array
    {
        $file = self::tripCacheFile();
        if (!file_exists($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['cached_at'])) {
            return null;
        }

        if (time() - (int) $data['cached_at'] > self::HISTORICAL_CACHE_TTL) {
            return null;
        }

        return $data;
    }

    private static function writeTripCache(array $data): void
    {
        $file = self::tripCacheFile();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private static function readTrip2023Cache(): ?array
    {
        $file = self::trip2023CacheFile();
        if (!file_exists($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['cached_at'])) {
            return null;
        }

        if (time() - (int) $data['cached_at'] > self::HISTORICAL_CACHE_TTL) {
            return null;
        }

        return $data;
    }

    private static function writeTrip2023Cache(array $data): void
    {
        $file = self::trip2023CacheFile();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private static function readTrip2022Cache(): ?array
    {
        $file = self::trip2022CacheFile();
        if (!file_exists($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['cached_at'])) {
            return null;
        }

        if (time() - (int) $data['cached_at'] > self::HISTORICAL_CACHE_TTL) {
            return null;
        }

        return $data;
    }

    private static function writeTrip2022Cache(array $data): void
    {
        $file = self::trip2022CacheFile();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private static function readTrip2021Cache(): ?array
    {
        $file = self::trip2021CacheFile();
        if (!file_exists($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['cached_at'])) {
            return null;
        }

        if (time() - (int) $data['cached_at'] > self::HISTORICAL_CACHE_TTL) {
            return null;
        }

        return $data;
    }

    private static function writeTrip2021Cache(array $data): void
    {
        $file = self::trip2021CacheFile();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private static function readTrip2024Cache(): ?array
    {
        $file = self::trip2024CacheFile();
        if (!file_exists($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['cached_at'])) {
            return null;
        }

        if (time() - (int) $data['cached_at'] > self::HISTORICAL_CACHE_TTL) {
            return null;
        }

        return $data;
    }

    private static function writeTrip2024Cache(array $data): void
    {
        $file = self::trip2024CacheFile();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    // ── API fetch ────────────────────────────────────────────────────────────

    private static function fetchFromApi(): ?array
    {
        $params = http_build_query([
            'latitude'       => self::LAT,
            'longitude'      => self::LON,
            'timezone'       => self::TIMEZONE,
            'forecast_days'  => self::FORECAST_DAYS,
            'wind_speed_unit' => 'kmh',
            'current'        => implode(',', [
                'temperature_2m',
                'apparent_temperature',
                'relative_humidity_2m',
                'weather_code',
                'wind_speed_10m',
                'wind_gusts_10m',
                'is_day',
            ]),
            'daily'          => implode(',', [
                'weather_code',
                'temperature_2m_max',
                'temperature_2m_min',
                'precipitation_probability_max',
                'precipitation_sum',
                'wind_speed_10m_max',
                'wind_gusts_10m_max',
                'sunrise',
                'sunset',
            ]),
        ]);

        $url   = self::API_URL . '?' . $params;
        $delay = 1_000_000; // 1 s initial backoff

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 10,
                    'header'        => "User-Agent: HutApp/1.0\r\n",
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);

            $statusCode = 200;
            if (!empty($http_response_header)) {
                preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m);
                $statusCode = (int) ($m[1] ?? 200);
            }

            if ($body !== false && $statusCode === 200) {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && isset($decoded['current'], $decoded['daily'])) {
                    return $decoded;
                }
                // Unexpected shape — no retry
                return null;
            }

            if (in_array($statusCode, [429, 503], true) && $attempt < self::MAX_RETRIES - 1) {
                usleep($delay);
                $delay *= 2;
            } else {
                break;
            }
        }

        return null;
    }

    private static function fetchTripHistoryFromApi(): ?array
    {
        return self::fetchTripHistoryForRange(self::TRIP_START_DATE, self::TRIP_END_DATE);
    }

    private static function fetchTripHistoryForRange(string $startDate, string $endDate): ?array
    {
        $params = http_build_query([
            'latitude'        => self::LAT,
            'longitude'       => self::LON,
            'timezone'        => self::TIMEZONE,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'wind_speed_unit' => 'kmh',
            'daily'           => implode(',', [
                'weather_code',
                'temperature_2m_max',
                'temperature_2m_min',
                'precipitation_sum',
                'precipitation_hours',
                'wind_speed_10m_max',
            ]),
        ]);

        $url   = self::HISTORICAL_API_URL . '?' . $params;
        $delay = 1_000_000; // 1 s initial backoff

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $context = stream_context_create([
                'http' => [
                    'timeout'       => 10,
                    'header'        => "User-Agent: HutApp/1.0\r\n",
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);

            $statusCode = 200;
            if (!empty($http_response_header)) {
                preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m);
                $statusCode = (int) ($m[1] ?? 200);
            }

            if ($body !== false && $statusCode === 200) {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && isset($decoded['daily'])) {
                    return $decoded;
                }
                return null;
            }

            if (in_array($statusCode, [429, 503], true) && $attempt < self::MAX_RETRIES - 1) {
                usleep($delay);
                $delay *= 2;
            } else {
                break;
            }
        }

        return null;
    }

    // ── WMO weather code mapping ─────────────────────────────────────────────

    /**
     * Returns ['icon' => string, 'label' => string] for a WMO weather code.
     * Icon names are Meteocons identifiers (basmilius/meteocons, MIT license).
     * Pass $isDay = true for day variants, false for night variants.
     */
    public static function wmoInfo(int $code, bool $isDay = true): array
    {
        $d = $isDay ? 'day' : 'night';

        return match (true) {
            $code === 0  => ['icon' => "clear-$d",                    'label' => 'Clear sky'],
            $code === 1  => ['icon' => "mostly-clear-$d",             'label' => 'Mainly clear'],
            $code === 2  => ['icon' => "partly-cloudy-$d",            'label' => 'Partly cloudy'],
            $code === 3  => ['icon' => 'overcast',                    'label' => 'Overcast'],
            $code === 45 => ['icon' => "fog-$d",                      'label' => 'Fog'],
            $code === 48 => ['icon' => "fog-$d",                      'label' => 'Rime fog'],
            $code === 51 => ['icon' => 'drizzle',                     'label' => 'Light drizzle'],
            $code === 53 => ['icon' => 'drizzle',                     'label' => 'Moderate drizzle'],
            $code === 55 => ['icon' => 'drizzle',                     'label' => 'Dense drizzle'],
            $code === 56 => ['icon' => 'sleet',                       'label' => 'Light freezing drizzle'],
            $code === 57 => ['icon' => 'sleet',                       'label' => 'Heavy freezing drizzle'],
            $code === 61 => ['icon' => 'rain',                        'label' => 'Slight rain'],
            $code === 63 => ['icon' => 'rain',                        'label' => 'Moderate rain'],
            $code === 65 => ['icon' => 'extreme-rain',                'label' => 'Heavy rain'],
            $code === 66 => ['icon' => 'sleet',                       'label' => 'Light freezing rain'],
            $code === 67 => ['icon' => 'sleet',                       'label' => 'Heavy freezing rain'],
            $code === 71 => ['icon' => 'snow',                        'label' => 'Slight snow'],
            $code === 73 => ['icon' => 'snow',                        'label' => 'Moderate snow'],
            $code === 75 => ['icon' => 'extreme-snow',                'label' => 'Heavy snow'],
            $code === 77 => ['icon' => 'snow',                        'label' => 'Snow grains'],
            $code === 80 => ['icon' => "partly-cloudy-$d-rain",       'label' => 'Slight showers'],
            $code === 81 => ['icon' => "partly-cloudy-$d-rain",       'label' => 'Moderate showers'],
            $code === 82 => ['icon' => 'extreme-rain',                'label' => 'Heavy showers'],
            $code === 85 => ['icon' => "partly-cloudy-$d-snow",       'label' => 'Slight snow showers'],
            $code === 86 => ['icon' => "partly-cloudy-$d-snow",       'label' => 'Heavy snow showers'],
            $code === 95 => ['icon' => 'thunderstorms',              'label' => 'Thunderstorm'],
            $code === 96 => ['icon' => 'thunderstorms-rain',          'label' => 'Thunderstorm with hail'],
            $code === 99 => ['icon' => 'thunderstorms-rain',          'label' => 'Thunderstorm with heavy hail'],
            default      => ['icon' => 'not-available',               'label' => 'Unknown'],
        };
    }

    /**
     * Returns the Meteocons CDN URL for a given icon name.
     */
    public static function iconUrl(string $iconName): string
    {
        // Strip any characters outside safe icon name alphabet
        $safe = preg_replace('/[^a-z0-9\-]/', '', strtolower($iconName));
        return self::ICON_CDN_BASE . $safe . '.svg';
    }
}
