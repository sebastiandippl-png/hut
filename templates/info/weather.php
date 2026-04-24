<?php
use Hut\WeatherForecast;
$pageTitle = 'Weather';
require __DIR__ . '/../partials/header.php';

/**
 * View variables supplied by WeatherController::showWeather():
 *   $error    — string|null  friendly error message when data unavailable
 *   $today    — array|null   current conditions + today daily values
 *   $forecast — array        14-element array of daily rows
 */

// Helper: format a float value with a unit or return an em-dash
$fmt = static function (mixed $value, string $unit, int $decimals = 0): string {
    if ($value === null) {
        return '—';
    }
    return number_format((float) $value, $decimals) . ' ' . $unit;
};

// Helper: format a date string (YYYY-MM-DD) as "Mon DD.MM."
$fmtDate = static function (string $date): string {
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    if ($dt === false) {
        return htmlspecialchars($date);
    }
    return $dt->format('D, d.m.');
};
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
    <section class="weather-forecast card" aria-label="14-day forecast">
        <h2>14-Day Forecast</h2>
        <div class="weather-forecast__list">
            <?php foreach ($forecast as $day): ?>
                <div class="weather-forecast__row">
                    <span class="weather-forecast__date"><?= htmlspecialchars($fmtDate($day['date'])) ?></span>
                    <img
                        class="weather-forecast__icon"
                        src="<?= htmlspecialchars(WeatherForecast::iconUrl($day['icon'])) ?>"
                        alt="<?= htmlspecialchars($day['label']) ?>"
                        title="<?= htmlspecialchars($day['label']) ?>"
                        width="32"
                        height="32"
                        loading="lazy"
                    >
                    <span class="weather-forecast__label"><?= htmlspecialchars($day['label']) ?></span>
                    <span class="weather-forecast__temps">
                        <span class="weather-forecast__max" title="High">↑ <?= $fmt($day['temp_max'], '°C') ?></span>
                        <span class="weather-forecast__min" title="Low">↓ <?= $fmt($day['temp_min'], '°C') ?></span>
                    </span>
                    <span class="weather-forecast__precip" title="Precipitation">
                        💧 <?= $fmt($day['precip_prob'], '%') ?> · <?= $fmt($day['precip_sum'], 'mm', 1) ?>
                    </span>
                    <span class="weather-forecast__wind" title="Max wind / gusts">
                        💨 <?= $fmt($day['wind_max'], 'km/h') ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

<?php endif; ?>

<p class="weather-attribution">
    Weather data: <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Open-Meteo</a> (open-source, free for non-commercial use) ·
    Icons: <a href="https://github.com/basmilius/meteocons" target="_blank" rel="noopener noreferrer">Meteocons</a> by Bas Milius (MIT license)
</p>

<?php require __DIR__ . '/../partials/footer.php'; ?>
