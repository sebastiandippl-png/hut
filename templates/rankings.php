<?php $pageTitle = 'Heart Rankings'; require __DIR__ . '/partials/header.php'; ?>

<div class="page-header">
    <h1>Heart Ranking</h1>
    <p class="page-header__sub">Our Most Hearted Games</p>
</div>

<?php if (empty($rankings)): ?>
    <p class="empty-state">No hearts yet. <a href="/collection">Browse Hut Menu</a> and heart your favorites!</p>
<?php else: ?>

    <div class="collection-filters">
        <div class="collection-filters__group">
            <span class="collection-filters__label">Complexity</span>
            <div class="filter-segs" data-complexity-filters data-rankings-complexity>
                <button class="filter-seg filter-seg--active" data-complexity="">All</button>
                <button class="filter-seg" data-complexity="light">Light</button>
                <button class="filter-seg" data-complexity="medium">Medium</button>
                <button class="filter-seg" data-complexity="complex">Complex</button>
            </div>
        </div>
    </div>

    <p class="results-count" data-rankings-count></p>

    <div class="game-grid" data-rankings-grid>
        <?php foreach ($rankings as $i => $game): ?>
            <?php
            $complexity = isset($game['averageweight']) && $game['averageweight'] !== null
                ? (float) $game['averageweight'] : null;
            $complexityAttr = $complexity !== null ? number_format($complexity, 5, '.', '') : '-1';
            ?>
            <article class="game-card"
                     data-id="<?= $game['id'] ?>"
                     data-overall-rank="<?= $i + 1 ?>"
                     data-complexity="<?= $complexityAttr ?>">
                <?php $thumbUrl = \Hut\BggThingFetcher::localUrl($game['id']); ?>
                <?php if ($thumbUrl): ?>
                    <a href="/games/<?= $game['id'] ?>">
                        <img class="game-card__thumb"
                             src="<?= htmlspecialchars($thumbUrl) ?>"
                             alt="<?= htmlspecialchars($game['name']) ?>"
                             loading="lazy">
                    </a>
                <?php else: ?>
                    <a href="/games/<?= $game['id'] ?>" class="game-card__thumb-placeholder"><?= $i + 1 ?></a>
                <?php endif; ?>

                <div class="game-card__body">
                    <h2 class="game-card__title">
                        <a href="/games/<?= $game['id'] ?>"><?= htmlspecialchars($game['name']) ?></a>
                        <?php if ($game['yearpublished']): ?>
                            <span class="game-card__year">(<?= $game['yearpublished'] ?>)</span>
                        <?php endif; ?>
                    </h2>

                    <div class="game-card__meta">
                        <span class="rankings__position" title="Ranking">🏆 #<?= $i + 1 ?></span>
                        <?php if ((int) ($game['rank'] ?? 0) > 0): ?>
                            <span title="BGG rank">📊 <?= (int) $game['rank'] ?></span>
                        <?php endif; ?>
                        <span title="Hearts">💖 <?= (int) $game['hearts'] ?></span>
                    </div>

                    <?php if (!empty($game['hearted_by'])): ?>
                        <p class="rankings__hearted-by">♥ <?= htmlspecialchars((string) $game['hearted_by']) ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
