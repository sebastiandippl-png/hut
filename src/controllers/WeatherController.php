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
        $error = null;
        $today    = null;
        $forecast = [];

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

        require __DIR__ . '/../../templates/info/weather.php';
    }
}
