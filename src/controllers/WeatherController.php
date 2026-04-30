<?php

declare(strict_types=1);

namespace Hut\controllers;

use DateTime;
use Hut\Auth;
use Hut\WeatherForecast;

class WeatherController
{
    public static function showWeather(array $params): void
    {
        Auth::requireLogin();

        $raw   = WeatherForecast::get();
        $tripRaw = WeatherForecast::getTripHistory();
        $trip2024Raw = WeatherForecast::getTripHistory2024();
        $trip2023Raw = WeatherForecast::getTripHistory2023();
        $trip2022Raw = WeatherForecast::getTripHistory2022();
        $trip2021Raw = WeatherForecast::getTripHistory2021();
        $error = null;
        $today    = null;
        $forecast = [];
        $tripWeather = [];
        $tripWeatherError = null;
        $tripWeather2024 = [];
        $tripWeather2024Error = null;
        $tripWeather2023 = [];
        $tripWeather2023Error = null;
        $tripWeather2022 = [];
        $tripWeather2022Error = null;
        $tripWeather2021 = [];
        $tripWeather2021Error = null;

        if ($raw === null) {
            $error = 'Weather data is currently unavailable. Please try again later.';
        } else {
            $current = $raw['current'] ?? [];
            $daily   = $raw['daily']   ?? [];

            $isDay   = (bool) ($current['is_day'] ?? 1);
            $wmoCode = (int)  ($current['weather_code'] ?? 0);
            $wmoInfo = WeatherForecast::wmoInfo($wmoCode, $isDay);

            // Safely extract today's (index 0) daily values
            $todaySunriseRaw = $daily['sunrise'][0] ?? null;
            $todaySunsetRaw  = $daily['sunset'][0]  ?? null;

            $today = [
                'temp'        => $current['temperature_2m']      ?? null,
                'feels_like'  => $current['apparent_temperature'] ?? null,
                'humidity'    => $current['relative_humidity_2m'] ?? null,
                'wind_speed'  => $current['wind_speed_10m']       ?? null,
                'wind_gusts'  => $current['wind_gusts_10m']       ?? null,
                'weather_code' => $wmoCode,
                'label'       => $wmoInfo['label'],
                'icon'        => $wmoInfo['icon'],
                'is_day'      => $isDay,
                'temp_max'    => $daily['temperature_2m_max'][0]          ?? null,
                'temp_min'    => $daily['temperature_2m_min'][0]          ?? null,
                'precip_prob' => $daily['precipitation_probability_max'][0] ?? null,
                'precip_sum'  => $daily['precipitation_sum'][0]           ?? null,
                'wind_max'    => $daily['wind_speed_10m_max'][0]          ?? null,
                'gusts_max'   => $daily['wind_gusts_10m_max'][0]          ?? null,
                'sunrise'     => $todaySunriseRaw !== null
                    ? (new DateTime($todaySunriseRaw))->format('H:i') : null,
                'sunset'      => $todaySunsetRaw !== null
                    ? (new DateTime($todaySunsetRaw))->format('H:i')  : null,
            ];

            // Build 14-day forecast list
            foreach (($daily['time'] ?? []) as $i => $date) {
                $code    = (int) ($daily['weather_code'][$i] ?? 0);
                $info    = WeatherForecast::wmoInfo($code, true); // daily always uses day icon
                $srRaw   = $daily['sunrise'][$i] ?? null;
                $ssRaw   = $daily['sunset'][$i]  ?? null;

                $forecast[] = [
                    'date'        => $date,
                    'weather_code' => $code,
                    'label'       => $info['label'],
                    'icon'        => $info['icon'],
                    'temp_max'    => $daily['temperature_2m_max'][$i]          ?? null,
                    'temp_min'    => $daily['temperature_2m_min'][$i]          ?? null,
                    'precip_prob' => $daily['precipitation_probability_max'][$i] ?? null,
                    'precip_sum'  => $daily['precipitation_sum'][$i]           ?? null,
                    'wind_max'    => $daily['wind_speed_10m_max'][$i]          ?? null,
                    'gusts_max'   => $daily['wind_gusts_10m_max'][$i]          ?? null,
                    'sunrise'     => $srRaw !== null ? (new DateTime($srRaw))->format('H:i') : null,
                    'sunset'      => $ssRaw !== null ? (new DateTime($ssRaw))->format('H:i')  : null,
                ];
            }
        }

        if ($tripRaw === null) {
            $tripWeatherError = 'Historical trip weather is currently unavailable. Please try again later.';
        } else {
            $daily = $tripRaw['daily'] ?? [];

            foreach (($daily['time'] ?? []) as $i => $date) {
                $code = (int) ($daily['weather_code'][$i] ?? 0);
                $info = WeatherForecast::wmoInfo($code, true);

                $tripWeather[] = [
                    'date'         => $date,
                    'weather_code' => $code,
                    'label'        => $info['label'],
                    'icon'         => $info['icon'],
                    'icon_url'     => WeatherForecast::iconUrl($info['icon']),
                    'temp_max'     => $daily['temperature_2m_max'][$i] ?? null,
                    'temp_min'     => $daily['temperature_2m_min'][$i] ?? null,
                    'precip_sum'      => $daily['precipitation_sum'][$i] ?? null,
                    'precip_hours'    => $daily['precipitation_hours'][$i] ?? null,
                    'sunshine_hours'  => isset($daily['sunshine_duration'][$i]) ? (float)$daily['sunshine_duration'][$i] / 3600.0 : null,
                    'wind_max'        => $daily['wind_speed_10m_max'][$i] ?? null,
                ];
            }

            if ($tripWeather === []) {
                $tripWeatherError = 'Historical trip weather is currently unavailable. Please try again later.';
            }
        }

        if ($trip2024Raw === null) {
            $tripWeather2024Error = 'Historical trip weather is currently unavailable. Please try again later.';
        } else {
            $daily = $trip2024Raw['daily'] ?? [];

            foreach (($daily['time'] ?? []) as $i => $date) {
                $code = (int) ($daily['weather_code'][$i] ?? 0);
                $info = WeatherForecast::wmoInfo($code, true);

                $tripWeather2024[] = [
                    'date'         => $date,
                    'weather_code' => $code,
                    'label'        => $info['label'],
                    'icon'         => $info['icon'],
                    'icon_url'     => WeatherForecast::iconUrl($info['icon']),
                    'temp_max'     => $daily['temperature_2m_max'][$i] ?? null,
                    'temp_min'     => $daily['temperature_2m_min'][$i] ?? null,
                    'precip_sum'      => $daily['precipitation_sum'][$i] ?? null,
                    'precip_hours'    => $daily['precipitation_hours'][$i] ?? null,
                    'sunshine_hours'  => isset($daily['sunshine_duration'][$i]) ? (float)$daily['sunshine_duration'][$i] / 3600.0 : null,
                    'wind_max'        => $daily['wind_speed_10m_max'][$i] ?? null,
                ];
            }

            if ($tripWeather2024 === []) {
                $tripWeather2024Error = 'Historical trip weather is currently unavailable. Please try again later.';
            }
        }

        if ($trip2023Raw === null) {
            $tripWeather2023Error = 'Historical trip weather is currently unavailable. Please try again later.';
        } else {
            $daily = $trip2023Raw['daily'] ?? [];

            foreach (($daily['time'] ?? []) as $i => $date) {
                $code = (int) ($daily['weather_code'][$i] ?? 0);
                $info = WeatherForecast::wmoInfo($code, true);

                $tripWeather2023[] = [
                    'date'         => $date,
                    'weather_code' => $code,
                    'label'        => $info['label'],
                    'icon'         => $info['icon'],
                    'icon_url'     => WeatherForecast::iconUrl($info['icon']),
                    'temp_max'     => $daily['temperature_2m_max'][$i] ?? null,
                    'temp_min'     => $daily['temperature_2m_min'][$i] ?? null,
                    'precip_sum'      => $daily['precipitation_sum'][$i] ?? null,
                    'precip_hours'    => $daily['precipitation_hours'][$i] ?? null,
                    'sunshine_hours'  => isset($daily['sunshine_duration'][$i]) ? (float)$daily['sunshine_duration'][$i] / 3600.0 : null,
                    'wind_max'        => $daily['wind_speed_10m_max'][$i] ?? null,
                ];
            }

            if ($tripWeather2023 === []) {
                $tripWeather2023Error = 'Historical trip weather is currently unavailable. Please try again later.';
            }
        }

        if ($trip2022Raw === null) {
            $tripWeather2022Error = 'Historical trip weather is currently unavailable. Please try again later.';
        } else {
            $daily = $trip2022Raw['daily'] ?? [];

            foreach (($daily['time'] ?? []) as $i => $date) {
                $code = (int) ($daily['weather_code'][$i] ?? 0);
                $info = WeatherForecast::wmoInfo($code, true);

                $tripWeather2022[] = [
                    'date'         => $date,
                    'weather_code' => $code,
                    'label'        => $info['label'],
                    'icon'         => $info['icon'],
                    'icon_url'     => WeatherForecast::iconUrl($info['icon']),
                    'temp_max'     => $daily['temperature_2m_max'][$i] ?? null,
                    'temp_min'     => $daily['temperature_2m_min'][$i] ?? null,
                    'precip_sum'      => $daily['precipitation_sum'][$i] ?? null,
                    'precip_hours'    => $daily['precipitation_hours'][$i] ?? null,
                    'sunshine_hours'  => isset($daily['sunshine_duration'][$i]) ? (float)$daily['sunshine_duration'][$i] / 3600.0 : null,
                    'wind_max'        => $daily['wind_speed_10m_max'][$i] ?? null,
                ];
            }

            if ($tripWeather2022 === []) {
                $tripWeather2022Error = 'Historical trip weather is currently unavailable. Please try again later.';
            }
        }

        if ($trip2021Raw === null) {
            $tripWeather2021Error = 'Historical trip weather is currently unavailable. Please try again later.';
        } else {
            $daily = $trip2021Raw['daily'] ?? [];

            foreach (($daily['time'] ?? []) as $i => $date) {
                $code = (int) ($daily['weather_code'][$i] ?? 0);
                $info = WeatherForecast::wmoInfo($code, true);

                $tripWeather2021[] = [
                    'date'         => $date,
                    'weather_code' => $code,
                    'label'        => $info['label'],
                    'icon'         => $info['icon'],
                    'icon_url'     => WeatherForecast::iconUrl($info['icon']),
                    'temp_max'     => $daily['temperature_2m_max'][$i] ?? null,
                    'temp_min'     => $daily['temperature_2m_min'][$i] ?? null,
                    'precip_sum'      => $daily['precipitation_sum'][$i] ?? null,
                    'precip_hours'    => $daily['precipitation_hours'][$i] ?? null,
                    'sunshine_hours'  => isset($daily['sunshine_duration'][$i]) ? (float)$daily['sunshine_duration'][$i] / 3600.0 : null,
                    'wind_max'        => $daily['wind_speed_10m_max'][$i] ?? null,
                ];
            }

            if ($tripWeather2021 === []) {
                $tripWeather2021Error = 'Historical trip weather is currently unavailable. Please try again later.';
            }
        }

        // --- 5-year trip comparison/overlay aggregation ---
        $tripYears = [
            '2025' => $tripWeather,
            '2024' => $tripWeather2024,
            '2023' => $tripWeather2023,
            '2022' => $tripWeather2022,
            '2021' => $tripWeather2021,
        ];
        $tripStats = [];
        $overlayData = [];
        $sunHoursByYear = [];
        foreach ($tripYears as $year => $rows) {
            $coldest = null;
            $warmest = null;
            $rain = 0.0;
            $sun = 0.0;
            $avgTemps = [];
            $avgWinds = [];
            $maxWind = null;
            foreach ($rows as $i => $row) {
                $min = isset($row['temp_min']) ? (float)$row['temp_min'] : null;
                $max = isset($row['temp_max']) ? (float)$row['temp_max'] : null;
                $wind = isset($row['wind_max']) ? (float)$row['wind_max'] : null;
                if ($min !== null) {
                    $coldest = ($coldest === null) ? $min : min($coldest, $min);
                }
                if ($max !== null) {
                    $warmest = ($warmest === null) ? $max : max($warmest, $max);
                }
                if (isset($row['precip_sum'])) {
                    $rain += (float)$row['precip_sum'];
                }
                if (isset($row['sunshine_hours'])) {
                    $sun += (float)$row['sunshine_hours'];
                }
                if ($wind !== null) {
                    $avgWinds[] = $wind;
                    $maxWind = ($maxWind === null) ? $wind : max($maxWind, $wind);
                }
                // For overlay: mean temp per day
                if ($min !== null && $max !== null) {
                    $avgTemps[] = ($min + $max) / 2.0;
                } else {
                    $avgTemps[] = null;
                }
            }
            // ...existing code for $tripStats[$year] and $overlayData[$year]...
            $avgTemp = null;
            $avgValid = array_filter($avgTemps, static fn($v) => $v !== null);
            if (count($avgValid) > 0) {
                $avgTemp = array_sum($avgValid) / count($avgValid);
            }
            $avgWind = null;
            if (count($avgWinds) > 0) {
                $avgWind = array_sum($avgWinds) / count($avgWinds);
            }
            $tripStats[$year] = [
                'coldest' => $coldest,
                'warmest' => $warmest,
                'rain'    => $rain,
                'sun'     => $sun,
                'avgTemps'=> $avgTemps,
                'avgTemp' => $avgTemp,
                'days'    => count($rows),
                'avgWind' => $avgWind,
                'maxWind' => $maxWind,
            ];
            $overlayData[$year] = $avgTemps;
        }
        // Find year with most sunshine hours
        $mostSunYear = null;
        foreach ($tripStats as $year => $s) {
            if ($mostSunYear === null || $s['sun'] > $tripStats[$mostSunYear]['sun']) {
                $mostSunYear = $year;
            }
        }

        // Find winners (coldest/warmest = lowest/highest avg temp, windiest = highest avg wind)
        $summary = [
            'coldest' => null,
            'warmest' => null,
            'rain'    => null,
            'sun'     => null,
            'avgTemp' => null,
            'windiest' => null,
        ];
        foreach ($tripStats as $year => $s) {
            if ($summary['coldest'] === null || $s['avgTemp'] < $tripStats[$summary['coldest']]['avgTemp']) {
                $summary['coldest'] = $year;
            }
            if ($summary['warmest'] === null || $s['avgTemp'] > $tripStats[$summary['warmest']]['avgTemp']) {
                $summary['warmest'] = $year;
            }
            if ($summary['rain'] === null || $s['rain'] > $tripStats[$summary['rain']]['rain']) {
                $summary['rain'] = $year;
            }
            if ($summary['sun'] === null || $s['sun'] < $tripStats[$summary['sun']]['sun']) {
                $summary['sun'] = $year;
            }
            if ($summary['avgTemp'] === null || $s['avgTemp'] > $tripStats[$summary['avgTemp']]['avgTemp']) {
                $summary['avgTemp'] = $year;
            }
            if ($summary['windiest'] === null || $s['avgWind'] > $tripStats[$summary['windiest']]['avgWind']) {
                $summary['windiest'] = $year;
            }
        }

        require __DIR__ . '/../../templates/info/weather.php';
    }
}
