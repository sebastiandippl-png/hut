<?php

declare(strict_types=1);

use Hut\Auth;
use Hut\BggThingFetcher;
use Hut\WeatherForecast;

/**
 * View variables supplied by GameController::landing():
 *   $totalGames    — int
 *   $complexityData— list<array{label:string,count:int}>
 *   $latestAdded   — array{id,name,added_by,added_at}|null
 *   $latestHearted — array{id,name,hearted_by,hearted_at}|null
 *   $weatherToday  — array{temp,feels_like,temp_max,temp_min,label,icon}|null
 */

$pageTitle = 'Dashboard';
require __DIR__ . '/../partials/header.php';

$residentNameMap = \Hut\Resident::firstNameToIdMap();

// Helper: format a float value or return em-dash
$fmt = static function (mixed $value, string $unit, int $decimals = 0): string {
    if ($value === null) {
        return '—';
    }
    return number_format((float) $value, $decimals) . ' ' . $unit;
};

// Helper: format a datetime string for display (date + time, compact)
$fmtDatetime = static function (string $dt): string {
    $parsed = \DateTime::createFromFormat('Y-m-d H:i:s', $dt)
        ?: \DateTime::createFromFormat('Y-m-d H:i', $dt)
        ?: false;
    if ($parsed === false) {
        return htmlspecialchars($dt);
    }
    return $parsed->format('d.m.Y');
};

// Helper: wrap a resident first-name in a profile link if possible
$linkifyName = static function (string $fullName) use ($residentNameMap): string {
    $first = \Hut\Auth::firstName($fullName);
    if ($first !== '' && isset($residentNameMap[$first])) {
        return '<a href="/residents/' . $residentNameMap[$first] . '">' . htmlspecialchars($first) . '</a>';
    }
    return htmlspecialchars($first !== '' ? $first : $fullName);
};

$complexityJson = json_encode($complexityData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$complexityJson = is_string($complexityJson) ? $complexityJson : '[]';
?>

<div class="page-header">
    <h1>Welcome back, <?= htmlspecialchars(Auth::firstName(Auth::user()['name'])) ?>!</h1>
    <p class="page-header__sub">Here's what's going on at the hut.</p>
</div>

<div class="landing-grid" data-landing-page>

    <?php /* ── Card 1: Selected Games KPI ─────────────────────────────── */ ?>
    <a href="/collection" class="landing-card landing-card--kpi landing-card--link" aria-label="View hut collection">
        <span class="landing-kpi__icon" aria-hidden="true">🎲</span>
        <div class="landing-kpi__body">
            <p class="landing-kpi__value"><?= (int) $totalGames ?></p>
            <p class="landing-kpi__label">Selected games</p>
        </div>
        <span class="landing-card__arrow" aria-hidden="true">→</span>
    </a>

    <?php /* ── Card 2: Last added game ───────────────────────────────── */ ?>
    <section class="landing-card landing-card--activity" aria-labelledby="landing-added-title">
        <h2 id="landing-added-title" class="landing-card__heading">➕ Last added</h2>
        <?php if ($latestAdded !== null): ?>
            <?php $addedThumb = BggThingFetcher::localUrl((int) $latestAdded['id']); ?>
            <div class="landing-activity">
                <?php if ($addedThumb): ?>
                    <a href="/games/<?= (int) $latestAdded['id'] ?>" tabindex="-1" aria-hidden="true">
                        <img class="landing-activity__thumb"
                             src="<?= htmlspecialchars($addedThumb) ?>"
                             alt="<?= htmlspecialchars((string) $latestAdded['name']) ?>"
                             loading="lazy">
                    </a>
                <?php else: ?>
                    <span class="landing-activity__thumb landing-activity__thumb--placeholder">BGG</span>
                <?php endif; ?>
                <div class="landing-activity__info">
                    <a class="landing-activity__name" href="/games/<?= (int) $latestAdded['id'] ?>">
                        <?= htmlspecialchars((string) $latestAdded['name']) ?>
                    </a>
                    <p class="landing-activity__meta">
                        by <?= $linkifyName((string) $latestAdded['added_by']) ?>
                    </p>
                    <p class="landing-activity__date"><?= $fmtDatetime((string) $latestAdded['added_at']) ?></p>
                </div>
            </div>
        <?php else: ?>
            <p class="empty-state">No games have been added yet.</p>
        <?php endif; ?>
    </section>

    <?php /* ── Card 3: Last hearted game ─────────────────────────────── */ ?>
    <section class="landing-card landing-card--activity" aria-labelledby="landing-hearted-title">
        <h2 id="landing-hearted-title" class="landing-card__heading">♥ Last hearted</h2>
        <?php if ($latestHearted !== null): ?>
            <?php $heartedThumb = BggThingFetcher::localUrl((int) $latestHearted['id']); ?>
            <div class="landing-activity">
                <?php if ($heartedThumb): ?>
                    <a href="/games/<?= (int) $latestHearted['id'] ?>" tabindex="-1" aria-hidden="true">
                        <img class="landing-activity__thumb"
                             src="<?= htmlspecialchars($heartedThumb) ?>"
                             alt="<?= htmlspecialchars((string) $latestHearted['name']) ?>"
                             loading="lazy">
                    </a>
                <?php else: ?>
                    <span class="landing-activity__thumb landing-activity__thumb--placeholder">BGG</span>
                <?php endif; ?>
                <div class="landing-activity__info">
                    <a class="landing-activity__name" href="/games/<?= (int) $latestHearted['id'] ?>">
                        <?= htmlspecialchars((string) $latestHearted['name']) ?>
                    </a>
                    <p class="landing-activity__meta">
                        by <?= $linkifyName((string) $latestHearted['hearted_by']) ?>
                    </p>
                    <p class="landing-activity__date"><?= $fmtDatetime((string) $latestHearted['hearted_at']) ?></p>
                </div>
            </div>
        <?php else: ?>
            <p class="empty-state">No games have been hearted yet.</p>
        <?php endif; ?>
    </section>

    <?php /* ── Card 4: Weather ──────────────────────────────────────────── */ ?>
    <a href="/news/weather" class="landing-card landing-card--weather landing-card--link" aria-label="View full weather forecast">
        <h2 class="landing-card__heading">🌤 Weather <span class="landing-card__sub">St. Veit im Pongau</span></h2>
        <?php if ($weatherToday !== null): ?>
            <div class="landing-weather">
                <img class="landing-weather__icon"
                     src="<?= htmlspecialchars(WeatherForecast::iconUrl($weatherToday['icon'])) ?>"
                     alt="<?= htmlspecialchars($weatherToday['label']) ?>"
                     width="64" height="64" loading="lazy">
                <div class="landing-weather__main">
                    <span class="landing-weather__temp"><?= $fmt($weatherToday['temp'], '°C') ?></span>
                    <span class="landing-weather__label"><?= htmlspecialchars($weatherToday['label']) ?></span>
                </div>
                <div class="landing-weather__minmax">
                    <span title="Today's high">↑ <?= $fmt($weatherToday['temp_max'], '°C') ?></span>
                    <span title="Today's low">↓ <?= $fmt($weatherToday['temp_min'], '°C') ?></span>
                </div>
            </div>
        <?php else: ?>
            <p class="empty-state">Weather data unavailable.</p>
        <?php endif; ?>
        <span class="landing-card__arrow" aria-hidden="true">→</span>
    </a>

    <?php /* ── Card 4b: Most hearted game ────────────────────────────────── */ ?>
    <a href="/collection" class="landing-card landing-card--activity landing-card--link" aria-labelledby="landing-mosthearted-title">
        <h2 id="landing-mosthearted-title" class="landing-card__heading">🏆 Most hearted <span class="landing-card__arrow" aria-hidden="true">→</span></h2>
        <?php if ($mostHearted !== null): ?>
            <?php $mostHeartedThumb = BggThingFetcher::localUrl((int) $mostHearted['id']); ?>
            <div class="landing-activity">
                <?php if ($mostHeartedThumb): ?>
                    <span aria-hidden="true">
                        <img class="landing-activity__thumb"
                             src="<?= htmlspecialchars($mostHeartedThumb) ?>"
                             alt="<?= htmlspecialchars((string) $mostHearted['name']) ?>"
                             loading="lazy">
                    </span>
                <?php else: ?>
                    <span class="landing-activity__thumb landing-activity__thumb--placeholder">BGG</span>
                <?php endif; ?>
                <div class="landing-activity__info">
                    <span class="landing-activity__name"><?= htmlspecialchars((string) $mostHearted['name']) ?></span>
                    <p class="landing-activity__meta">
                        ♥ <?= (int) $mostHearted['hearts'] ?> heart<?= (int) $mostHearted['hearts'] !== 1 ? 's' : '' ?>
                    </p>
                    <p class="landing-activity__date"><?= htmlspecialchars((string) $mostHearted['hearted_by']) ?></p>
                </div>
            </div>
        <?php else: ?>
            <p class="empty-state">No hearted games yet.</p>
        <?php endif; ?>
    </a>

    <?php /* ── Card 5: Complexity Donut ─────────────────────────────────── */ ?>
    <a href="/games/statistics" class="landing-card landing-card--donut landing-card--link" aria-label="View full collection statistics"
       data-landing-complexity='<?= htmlspecialchars($complexityJson, ENT_QUOTES, 'UTF-8') ?>'>
        <h2 class="landing-card__heading">🧠 Complexity <span class="landing-card__arrow" aria-hidden="true">→</span></h2>
        <?php if ((int) $totalGames > 0): ?>
            <div class="landing-donut-wrap">
                <svg class="stats-pie" viewBox="0 0 220 220" role="img"
                     aria-label="Complexity distribution donut chart"
                     data-landing-pie-chart="complexity"></svg>
                <ul class="stats-legend" data-landing-pie-legend="complexity"></ul>
            </div>
        <?php else: ?>
            <p class="empty-state">No games selected yet.</p>
        <?php endif; ?>
    </a>

</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
