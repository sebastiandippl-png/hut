<?php
use Hut\WeatherForecast;
$pageTitle = 'Weather';
require __DIR__ . '/../partials/header.php';

/**
 * View variables supplied by WeatherController::showWeather():
 *   $error    — string|null  friendly error message when data unavailable
 *   $today    — array|null   current conditions + today daily values
 *   $forecast — array        14-element array of daily rows
 *   $tripWeather — array      historical trip weather rows
 *   $tripWeatherError — string|null error message for historical trip weather
 *   $tripWeather2024 — array      historical trip weather rows (2024)
 *   $tripWeather2024Error — string|null error message for historical 2024 trip weather
 */

// Helper: format a float value with a unit or return an em-dash
$fmt = static function (mixed $value, string $unit, int $decimals = 0): string {
    if ($value === null) {
        return '—';
    }
    return number_format((float) $value, $decimals) . ' ' . $unit;
};

$tripWeatherJson = json_encode($tripWeather ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($tripWeatherJson)) {
    $tripWeatherJson = '[]';
}

$forecastChart = array_map(static function (array $day): array {
    return [
        'date'      => $day['date'] ?? null,
        'temp_max'  => $day['temp_max'] ?? null,
        'temp_min'  => $day['temp_min'] ?? null,
        'precip_sum'=> $day['precip_sum'] ?? null,
        'icon_url'  => WeatherForecast::iconUrl((string) ($day['icon'] ?? 'not-available')),
    ];
}, $forecast ?? []);

$forecastChartJson = json_encode($forecastChart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($forecastChartJson)) {
    $forecastChartJson = '[]';
}

$tripWeather2024Json = json_encode($tripWeather2024 ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($tripWeather2024Json)) {
    $tripWeather2024Json = '[]';
}

$tripWeather2023Json = json_encode($tripWeather2023 ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($tripWeather2023Json)) {
    $tripWeather2023Json = '[]';
}

$tripWeather2022Json = json_encode($tripWeather2022 ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($tripWeather2022Json)) {
    $tripWeather2022Json = '[]';
}

$tripWeather2021Json = json_encode($tripWeather2021 ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($tripWeather2021Json)) {
    $tripWeather2021Json = '[]';
}
?>

<div class="page-header">
    <h1>Weather</h1>
    <p class="page-header__sub">St. Veit im Pongau · Europe/Vienna</p>
</div>

<?php if ($error !== null): ?>
    <div class="card">
        <p class="empty-state"><?= htmlspecialchars($error) ?></p>
    </div>
<?php else: ?>

    <?php /* ── Card A: Today ───────────────────────────────────────────── */ ?>
    <section class="weather-today card" aria-label="Today's weather">
        <div class="weather-today__header">
            <img
                class="weather-today__icon"
                src="<?= htmlspecialchars(WeatherForecast::iconUrl($today['icon'])) ?>"
                alt="<?= htmlspecialchars($today['label']) ?>"
                width="80"
                height="80"
                loading="eager"
            >
            <div class="weather-today__main">
                <span class="weather-today__temp"><?= $fmt($today['temp'], '°C') ?></span>
                <span class="weather-today__label"><?= htmlspecialchars($today['label']) ?></span>
            </div>
            <div class="weather-today__minmax">
                <span class="weather-today__max" title="Today's high">↑ <?= $fmt($today['temp_max'], '°C') ?></span>
                <span class="weather-today__min" title="Today's low">↓ <?= $fmt($today['temp_min'], '°C') ?></span>
            </div>
        </div>

        <dl class="weather-today__details">
            <div class="weather-detail">
                <dt>Feels like</dt>
                <dd><?= $fmt($today['feels_like'], '°C') ?></dd>
            </div>
            <div class="weather-detail">
                <dt>Humidity</dt>
                <dd><?= $fmt($today['humidity'], '%') ?></dd>
            </div>
            <div class="weather-detail">
                <dt>Wind</dt>
                <dd><?= $fmt($today['wind_speed'], 'km/h') ?> <span class="weather-detail__sub">(gusts <?= $fmt($today['wind_gusts'], 'km/h') ?>)</span></dd>
            </div>
            <div class="weather-detail">
                <dt>Max wind</dt>
                <dd><?= $fmt($today['wind_max'], 'km/h') ?> <span class="weather-detail__sub">(gusts <?= $fmt($today['gusts_max'], 'km/h') ?>)</span></dd>
            </div>
            <div class="weather-detail">
                <dt>Precipitation</dt>
                <dd><?= $fmt($today['precip_prob'], '%') ?> chance · <?= $fmt($today['precip_sum'], 'mm', 1) ?></dd>
            </div>
            <?php if ($today['sunrise'] !== null || $today['sunset'] !== null): ?>
            <div class="weather-detail">
                <dt>Sunrise / Sunset</dt>
                <dd>
                    <?= $today['sunrise'] !== null ? htmlspecialchars($today['sunrise']) : '—' ?>
                    &nbsp;/&nbsp;
                    <?= $today['sunset']  !== null ? htmlspecialchars($today['sunset'])  : '—' ?>
                </dd>
            </div>
            <?php endif; ?>
        </dl>
    </section>

    <?php /* ── Card B: 14-day forecast ─────────────────────────────────── */ ?>
    <?php if (!empty($forecast)): ?>
    <section class="weather-forecast card" aria-label="14-day forecast" data-forecast-chart-data="<?= htmlspecialchars($forecastChartJson, ENT_QUOTES) ?>">
        <h2>14-Day Forecast</h2>

        <div class="weather-forecast__chart-wrap">
            <svg class="weather-forecast__chart" data-forecast-weather-chart viewBox="0 0 920 360" aria-label="14-day forecast chart"></svg>
        </div>

        <div class="weather-forecast__legend" aria-hidden="true">
            <span class="weather-forecast__legend-item"><span class="weather-forecast__swatch weather-forecast__swatch--max"></span> Max temperature</span>
            <span class="weather-forecast__legend-item"><span class="weather-forecast__swatch weather-forecast__swatch--min"></span> Min temperature</span>
            <span class="weather-forecast__legend-item"><span class="weather-forecast__swatch weather-forecast__swatch--precip"></span> Precipitation (mm)</span>
            <span class="weather-forecast__legend-item"><span class="weather-forecast__swatch weather-forecast__swatch--icon"></span> Meteocon per day</span>
        </div>
    </section>
    <?php endif; ?>

<?php endif; ?>

    <?php /* ── 5-year hut trip comparison and overlay ─────────────────── */ ?>
    <section class="weather-trip-compare card" aria-label="5-year hut trip weather comparison">
        <div class="weather-trip__head">
            <h2>Hut Trip Weather: 5-Year Comparison (2021–2025)</h2>
            <p class="weather-trip__sub">Summary and temperature overlay for all hut trips</p>
        </div>

        <div class="weather-trip-compare__summary">
            <table class="weather-trip-compare__table">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Coldest min</th>
                        <th>Warmest max</th>
                        <th>Avg temp</th>
                        <th>Avg wind</th>
                        <th>Max wind</th>
                        <th>Total rain (mm)</th>
                        <th>Total precip hours</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ([2025,2024,2023,2022,2021] as $year): ?>
                    <tr>
                        <td data-label="Year"><?= $year ?></td>
                        <td data-label="Coldest min"<?= ($tripStats[$year]['coldest'] === min(array_column($tripStats, 'coldest'))) ? ' class="weather-trip-compare__winner"' : '' ?>><?= isset($tripStats[$year]['coldest']) ? number_format($tripStats[$year]['coldest'], 1) . ' °C' : '—' ?></td>
                        <td data-label="Warmest max"<?= $summary['warmest'] == $year ? ' class="weather-trip-compare__winner"' : '' ?>><?= isset($tripStats[$year]['warmest']) ? number_format($tripStats[$year]['warmest'], 1) . ' °C' : '—' ?></td>
                        <td data-label="Avg temp"<?= $summary['avgTemp'] == $year ? ' class="weather-trip-compare__winner"' : '' ?>><?= isset($tripStats[$year]['avgTemp']) ? number_format($tripStats[$year]['avgTemp'], 1) . ' °C' : '—' ?></td>
                        <td data-label="Avg wind"<?= $summary['windiest'] == $year ? ' class="weather-trip-compare__winner"' : '' ?>><?= isset($tripStats[$year]['avgWind']) ? number_format($tripStats[$year]['avgWind'], 1) . ' km/h' : '—' ?></td>
                        <td data-label="Max wind"<?= ($tripStats[$year]['maxWind'] === max(array_column($tripStats, 'maxWind'))) ? ' class="weather-trip-compare__winner"' : '' ?>><?= isset($tripStats[$year]['maxWind']) ? number_format($tripStats[$year]['maxWind'], 1) . ' km/h' : '—' ?></td>
                        <td data-label="Total rain (mm)"<?= $summary['rain'] == $year ? ' class="weather-trip-compare__winner"' : '' ?>><?= isset($tripStats[$year]['rain']) ? number_format($tripStats[$year]['rain'], 1) : '—' ?></td>
                        <td data-label="Total precip hours"<?= $summary['sun'] == $year ? ' class="weather-trip-compare__winner"' : '' ?>><?= isset($tripStats[$year]['sun']) ? number_format($tripStats[$year]['sun'], 1) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        </div>

        <div class="weather-forecast__chart-wrap">
            <svg class="weather-forecast__chart" data-trip-overlay-chart viewBox="0 0 920 360" aria-label="5-year average temperature overlay chart"></svg>
        </div>
        <div class="weather-forecast__legend" aria-hidden="true">
            <span class="weather-forecast__legend-item"><span class="weather-forecast__swatch" style="background: #e74c3c"></span>2025</span>
            <span class="weather-forecast__legend-item"><span class="weather-forecast__swatch" style="background: #f39c12"></span>2024</span>
            <span class="weather-forecast__legend-item"><span class="weather-forecast__swatch" style="background: #27ae60"></span>2023</span>
            <span class="weather-forecast__legend-item"><span class="weather-forecast__swatch" style="background: #2980b9"></span>2022</span>
            <span class="weather-forecast__legend-item"><span class="weather-forecast__swatch" style="background: #8e44ad"></span>2021</span>
        </div>
        <script>
        window.tripOverlayData = <?= json_encode($overlayData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        </script>
    </section>
    <section
        class="weather-trip card"
        aria-label="Hut trip weather history"
        data-trip-weather-chart-data="<?= htmlspecialchars($tripWeatherJson, ENT_QUOTES) ?>"
    >
        <div class="weather-trip__head">
            <h2>Hut Trip 2025 Weather (25.09. - 04.10.)</h2>
            <p class="weather-trip__sub">Historical weather in St. Veit im Pongau</p>
        </div>

        <?php if ($tripWeatherError !== null): ?>
            <p class="empty-state"><?= htmlspecialchars($tripWeatherError) ?></p>
        <?php elseif (empty($tripWeather)): ?>
            <p class="empty-state">No historical trip weather data available.</p>
        <?php else: ?>
            <div class="weather-trip__chart-wrap">
                <svg class="weather-trip__chart" data-trip-weather-chart viewBox="0 0 860 360" aria-label="Historical trip weather chart"></svg>
            </div>

            <div class="weather-trip__legend" aria-hidden="true">
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--max"></span> Max temperature</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--min"></span> Min temperature</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--precip"></span> Precipitation (mm)</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--icon"></span> Meteocon per day</span>
            </div>
        <?php endif; ?>
    </section>

    <?php /* ── Card D: 2024 hut trip weather history ───────────────────── */ ?>
    <section
        class="weather-trip card"
        aria-label="Hut trip weather history 2024"
        data-trip-weather-chart-data="<?= htmlspecialchars($tripWeather2024Json, ENT_QUOTES) ?>"
    >
        <div class="weather-trip__head">
            <h2>Hut Trip 2024 Weather (26.09. - 05.10.)</h2>
            <p class="weather-trip__sub">Historical weather in St. Veit im Pongau</p>
        </div>

        <?php if ($tripWeather2024Error !== null): ?>
            <p class="empty-state"><?= htmlspecialchars($tripWeather2024Error) ?></p>
        <?php elseif (empty($tripWeather2024)): ?>
            <p class="empty-state">No historical trip weather data available.</p>
        <?php else: ?>
            <div class="weather-trip__chart-wrap">
                <svg class="weather-trip__chart" data-trip-weather-chart viewBox="0 0 860 360" aria-label="Historical trip weather chart"></svg>
            </div>

            <div class="weather-trip__legend" aria-hidden="true">
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--max"></span> Max temperature</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--min"></span> Min temperature</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--precip"></span> Precipitation (mm)</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--icon"></span> Meteocon per day</span>
            </div>
        <?php endif; ?>
    </section>

    <?php /* ── Card E: 2023 hut trip weather history ───────────────────── */ ?>
    <section
        class="weather-trip card"
        aria-label="Hut trip weather history 2023"
        data-trip-weather-chart-data="<?= htmlspecialchars($tripWeather2023Json, ENT_QUOTES) ?>"
    >
        <div class="weather-trip__head">
            <h2>Hut Trip 2023 Weather (30.09. - 07.10.)</h2>
            <p class="weather-trip__sub">Historical weather in St. Veit im Pongau</p>
        </div>

        <?php if ($tripWeather2023Error !== null): ?>
            <p class="empty-state"><?= htmlspecialchars($tripWeather2023Error) ?></p>
        <?php elseif (empty($tripWeather2023)): ?>
            <p class="empty-state">No historical trip weather data available.</p>
        <?php else: ?>
            <div class="weather-trip__chart-wrap">
                <svg class="weather-trip__chart" data-trip-weather-chart viewBox="0 0 860 360" aria-label="Historical trip weather chart"></svg>
            </div>

            <div class="weather-trip__legend" aria-hidden="true">
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--max"></span> Max temperature</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--min"></span> Min temperature</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--precip"></span> Precipitation (mm)</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--icon"></span> Meteocon per day</span>
            </div>
        <?php endif; ?>
    </section>

    <?php /* ── Card F: 2022 hut trip weather history ───────────────────── */ ?>
    <section
        class="weather-trip card"
        aria-label="Hut trip weather history 2022"
        data-trip-weather-chart-data="<?= htmlspecialchars($tripWeather2022Json, ENT_QUOTES) ?>"
    >
        <div class="weather-trip__head">
            <h2>Hut Trip 2022 Weather (03.10. - 10.10.)</h2>
            <p class="weather-trip__sub">Historical weather in St. Veit im Pongau</p>
        </div>

        <?php if ($tripWeather2022Error !== null): ?>
            <p class="empty-state"><?= htmlspecialchars($tripWeather2022Error) ?></p>
        <?php elseif (empty($tripWeather2022)): ?>
            <p class="empty-state">No historical trip weather data available.</p>
        <?php else: ?>
            <div class="weather-trip__chart-wrap">
                <svg class="weather-trip__chart" data-trip-weather-chart viewBox="0 0 860 360" aria-label="Historical trip weather chart"></svg>
            </div>

            <div class="weather-trip__legend" aria-hidden="true">
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--max"></span> Max temperature</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--min"></span> Min temperature</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--precip"></span> Precipitation (mm)</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--icon"></span> Meteocon per day</span>
            </div>
        <?php endif; ?>
    </section>

    <?php /* ── Card G: 2021 hut trip weather history ───────────────────── */ ?>
    <section
        class="weather-trip card"
        aria-label="Hut trip weather history 2021"
        data-trip-weather-chart-data="<?= htmlspecialchars($tripWeather2021Json, ENT_QUOTES) ?>"
    >
        <div class="weather-trip__head">
            <h2>Hut Trip 2021 Weather (25.09. - 02.10.)</h2>
            <p class="weather-trip__sub">Historical weather in St. Veit im Pongau</p>
        </div>

        <?php if ($tripWeather2021Error !== null): ?>
            <p class="empty-state"><?= htmlspecialchars($tripWeather2021Error) ?></p>
        <?php elseif (empty($tripWeather2021)): ?>
            <p class="empty-state">No historical trip weather data available.</p>
        <?php else: ?>
            <div class="weather-trip__chart-wrap">
                <svg class="weather-trip__chart" data-trip-weather-chart viewBox="0 0 860 360" aria-label="Historical trip weather chart"></svg>
            </div>

            <div class="weather-trip__legend" aria-hidden="true">
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--max"></span> Max temperature</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--min"></span> Min temperature</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--precip"></span> Precipitation (mm)</span>
                <span class="weather-trip__legend-item"><span class="weather-trip__swatch weather-trip__swatch--icon"></span> Meteocon per day</span>
            </div>
        <?php endif; ?>
    </section>

<p class="weather-attribution">
    Weather data: <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Open-Meteo</a> (open-source, free for non-commercial use) ·
    Icons: <a href="https://github.com/basmilius/meteocons" target="_blank" rel="noopener noreferrer">Meteocons</a> by Bas Milius (MIT license)
</p>

<?php require __DIR__ . '/../partials/footer.php'; ?>
