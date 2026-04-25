<?php

declare(strict_types=1);

$pageTitle = 'Collection Statistics';
require __DIR__ . '/../partials/header.php';

$statisticsPayload = [
    'totalGames' => (int) $totalGames,
    'complexity' => $complexityData,
    'bestWith' => $bestWithData,
    'duration' => $durationData,
    'hearts' => $heartsData,
    'rankDistribution' => $rankDistributionData,
];

$statisticsJson = json_encode($statisticsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$statisticsJson = is_string($statisticsJson) ? $statisticsJson : '{}';
?>

<div class="page-header">
    <h1>Collection Statistics</h1>
    <p class="page-header__sub">Insights for the currently selected games planned for the hut.</p>
</div>

<div class="stats-page" data-statistics-page data-stats='<?= htmlspecialchars($statisticsJson, ENT_QUOTES, 'UTF-8') ?>'>
    <section class="stats-card stats-card--kpi" aria-label="Total selected games">
        <span class="stats-kpi__icon" aria-hidden="true">🎲</span>
        <div class="stats-kpi__body">
            <p class="stats-kpi__value" data-stats-total><?= (int) $totalGames ?></p>
            <p class="stats-kpi__label">Selected games</p>
        </div>
    </section>

    <?php if ((int) $totalGames === 0): ?>
        <section class="card stats-card--wide" aria-label="No data yet">
            <p class="empty-state">No games are selected for the hut yet. Add games in <a href="/games">Suggest</a> to unlock statistics.</p>
        </section>
    <?php else: ?>
        <section class="stats-card" aria-labelledby="stats-complexity-title">
            <h2 id="stats-complexity-title"><span class="stats-icon" aria-hidden="true">🧠</span> Complexity</h2>
            <div class="stats-chart-grid">
                <svg class="stats-pie" viewBox="0 0 220 220" role="img" aria-label="Complexity pie chart" data-pie-chart="complexity"></svg>
                <ul class="stats-legend" data-pie-legend="complexity"></ul>
            </div>
        </section>

        <section class="stats-card" aria-labelledby="stats-bestwith-title">
            <h2 id="stats-bestwith-title"><span class="stats-icon" aria-hidden="true">👥</span> Best with</h2>
            <div class="stats-chart-grid">
                <svg class="stats-pie" viewBox="0 0 220 220" role="img" aria-label="Best with value pie chart" data-pie-chart="bestWith"></svg>
                <ul class="stats-legend" data-pie-legend="bestWith"></ul>
            </div>
        </section>

        <section class="stats-card" aria-labelledby="stats-duration-title">
            <h2 id="stats-duration-title"><span class="stats-icon" aria-hidden="true">⏱</span> Max duration</h2>
            <div class="stats-chart-grid">
                <svg class="stats-pie" viewBox="0 0 220 220" role="img" aria-label="Max duration pie chart" data-pie-chart="duration"></svg>
                <ul class="stats-legend" data-pie-legend="duration"></ul>
            </div>
        </section>

        <section class="stats-card" aria-labelledby="stats-hearts-title">
            <h2 id="stats-hearts-title"><span class="stats-icon" aria-hidden="true">♥</span> Hearts</h2>
            <div class="stats-chart-grid">
                <svg class="stats-pie" viewBox="0 0 220 220" role="img" aria-label="Hearts pie chart" data-pie-chart="hearts"></svg>
                <ul class="stats-legend" data-pie-legend="hearts"></ul>
            </div>
        </section>

        <section class="stats-card stats-card--wide" aria-labelledby="stats-rank-title">
            <h2 id="stats-rank-title"><span class="stats-icon" aria-hidden="true">🏆</span> BGG rank distribution</h2>
            <div class="stats-rank-chart" data-rank-chart></div>
        </section>

        <section class="stats-card" aria-labelledby="stats-categories-title">
            <h2 id="stats-categories-title"><span class="stats-icon" aria-hidden="true">🏷️</span> Top categories</h2>
            <?php if (empty($topCategories)): ?>
                <p class="empty-state">No category data yet.</p>
            <?php else: ?>
                <ol class="stats-toplist">
                    <?php foreach ($topCategories as $i => $item): ?>
                        <li class="stats-toplist__item">
                            <span class="stats-toplist__rank"><?= $i + 1 ?></span>
                            <span class="stats-toplist__label"><?= htmlspecialchars($item['label']) ?></span>
                            <span class="stats-toplist__count"><?= (int) $item['count'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <section class="stats-card" aria-labelledby="stats-mechanics-title">
            <h2 id="stats-mechanics-title"><span class="stats-icon" aria-hidden="true">⚙️</span> Top mechanics</h2>
            <?php if (empty($topMechanics)): ?>
                <p class="empty-state">No mechanic data yet.</p>
            <?php else: ?>
                <ol class="stats-toplist">
                    <?php foreach ($topMechanics as $i => $item): ?>
                        <li class="stats-toplist__item">
                            <span class="stats-toplist__rank"><?= $i + 1 ?></span>
                            <span class="stats-toplist__label"><?= htmlspecialchars($item['label']) ?></span>
                            <span class="stats-toplist__count"><?= (int) $item['count'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <section class="stats-card" aria-labelledby="stats-designers-title">
            <h2 id="stats-designers-title"><span class="stats-icon" aria-hidden="true">✏️</span> Top designers</h2>
            <?php if (empty($topDesigners)): ?>
                <p class="empty-state">No designer data yet.</p>
            <?php else: ?>
                <ol class="stats-toplist">
                    <?php foreach ($topDesigners as $i => $item): ?>
                        <li class="stats-toplist__item">
                            <span class="stats-toplist__rank"><?= $i + 1 ?></span>
                            <span class="stats-toplist__label"><?= htmlspecialchars($item['label']) ?></span>
                            <span class="stats-toplist__count"><?= (int) $item['count'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
