<?php $pageTitle = 'Hut Choice'; require __DIR__ . '/../partials/header.php'; ?>

<div class="page-header">
    <h1>Hut Choice</h1>
    <p class="page-header__sub">Games added to the hut by at least one member</p>
</div>

<?php if (empty($games)): ?>
    <p class="empty-state">No games selected yet. <a href="/games">Suggest games</a> and add some!</p>
<?php else: ?>

    <div class="collection-filters">
        <div class="collection-filters__group">
            <label class="collection-filters__label" for="filterBestPlayers">Best players</label>
            <select class="collection-filters__select" id="filterBestPlayers" data-collection-filter="bestplayers">
                <option value="">Any</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6+</option>
            </select>
        </div>

        <div class="collection-filters__group">
            <label class="collection-filters__label" for="filterMaxPlayers">Supports N+ players</label>
            <select class="collection-filters__select" id="filterMaxPlayers" data-collection-filter="maxplayers">
                <option value="">Any</option>
                <option value="2">2+</option>
                <option value="3">3+</option>
                <option value="4">4+</option>
                <option value="5">5+</option>
                <option value="6">6+</option>
            </select>
        </div>

        <div class="collection-filters__group">
            <span class="collection-filters__label">Complexity</span>
            <div class="filter-segs" data-complexity-filters>
                <button class="filter-seg filter-seg--active" data-complexity="">All</button>
                <button class="filter-seg" data-complexity="light">Light</button>
                <button class="filter-seg" data-complexity="medium">Medium</button>
                <button class="filter-seg" data-complexity="complex">Complex</button>
            </div>
        </div>

        <div class="collection-filters__group">
            <label class="collection-filters__label" for="filterMaxPlaytime">Max playtime</label>
            <select class="collection-filters__select" id="filterMaxPlaytime" data-collection-filter="maxplaytime">
                <option value="">Any</option>
                <option value="30">≤ 30 min</option>
                <option value="60">≤ 60 min</option>
                <option value="90">≤ 90 min</option>
                <option value="120">≤ 120 min</option>
                <option value="121+">&gt; 120 min</option>
            </select>
        </div>

        <button class="btn btn--ghost collection-filters__reset" data-collection-filter-reset>Reset</button>
    </div>

    <p class="results-count" data-collection-count></p>

    <div class="collection-grid" data-collection-grid>
        <?php foreach ($games as $game): ?>
            <?php
            $thumbUrl = \Hut\BggThingFetcher::localUrl((int) $game['id']);
            $maxPlayers = isset($game['maxplayers']) ? (int) $game['maxplayers'] : 0;
            $maxPlaytime = isset($game['maxplaytime']) ? (int) $game['maxplaytime'] : 0;
            $bestPlayerCount = trim((string) ($game['best_playercount'] ?? ''));
            $bestPlayerCountNum = isset($game['best_playercount_num']) && $game['best_playercount_num'] !== null
                ? (int) $game['best_playercount_num']
                : -1;
            $hearts = (int) ($game['hearts'] ?? 0);
            $complexity = isset($game['averageweight']) && $game['averageweight'] !== null
                ? (float) $game['averageweight']
                : null;

            if ($complexity !== null) {
                if ($complexity <= 1.8) {
                    $complexityLabel = 'Light';
                } elseif ($complexity <= 2.8) {
                    $complexityLabel = 'Medium';
                } else {
                    $complexityLabel = 'Complex';
                }
            } else {
                $complexityLabel = null;
            }
            ?>
            <article class="collection-card"
                     data-bestplayercount="<?= $bestPlayerCountNum >= 0 ? $bestPlayerCountNum : -1 ?>"
                     data-maxplayers="<?= $maxPlayers > 0 ? $maxPlayers : -1 ?>"
                     data-maxplaytime="<?= $maxPlaytime > 0 ? $maxPlaytime : -1 ?>"
                     data-complexity="<?= $complexity !== null ? number_format($complexity, 5, '.', '') : -1 ?>"
                     data-hearts="<?= $hearts ?>">

                <a href="/games/<?= $game['id'] ?>" tabindex="-1" aria-hidden="true">
                    <?php if ($thumbUrl): ?>
                        <img class="collection-card__thumb"
                             src="<?= htmlspecialchars($thumbUrl) ?>"
                             alt="<?= htmlspecialchars($game['name']) ?>"
                             loading="lazy">
                    <?php else: ?>
                        <span class="collection-card__thumb collection-card__thumb--placeholder">BGG</span>
                    <?php endif; ?>
                </a>

                <div class="collection-card__body">
                    <h2 class="collection-card__title">
                        <a href="/games/<?= $game['id'] ?>"><?= htmlspecialchars($game['name']) ?></a>
                    </h2>

                    <div class="collection-card__stats">
                        <?php if ($bestPlayerCount !== ''): ?>
                            <span title="Best players">👥 Best: <?= htmlspecialchars($bestPlayerCount) ?></span>
                        <?php endif; ?>
                        <?php if ($maxPlayers > 0): ?>
                            <span title="Max players">Max <?= $maxPlayers ?></span>
                        <?php endif; ?>
                        <?php if ($maxPlaytime > 0): ?>
                            <span title="Max playtime">⏱ <?= $maxPlaytime ?> min</span>
                        <?php endif; ?>
                        <?php if ($complexityLabel !== null): ?>
                            <span title="Complexity: <?= number_format((float) $complexity, 2) ?>">🧠 <?= htmlspecialchars($complexityLabel) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="heart-controls" data-game-id="<?= $game['id'] ?>">
                        <button class="btn btn--heart <?= (int) $game['hearted_by_me'] === 1 ? 'btn--heart--active' : '' ?>"
                                data-heart-button
                                data-game-id="<?= $game['id'] ?>">
                            <?= (int) $game['hearted_by_me'] === 1 ? '♥ Hearted' : '♥ Heart' ?>
                        </button>
                        <span class="heart-summary"><span class="heart-tally"><?= $hearts ?></span> <span class="heart-label">heart<?= $hearts !== 1 ? 's' : '' ?></span></span>
                    </div>

                    <div class="collection-card__meta">
                        <p class="meta-row meta-row--owners">
                            <span class="meta-row__label">Owned by</span>
                            <span class="meta-row__value"><?= !empty($game['bgg_owned_by']) ? htmlspecialchars((string) $game['bgg_owned_by']) : 'No tracked owners' ?></span>
                        </p>
                        <p class="meta-row meta-row--hearts">
                            <span class="meta-row__label">Hearted by</span>
                            <span class="meta-row__value" data-hearted-by="<?= $game['id'] ?>" data-hearted-mode="names"><?= htmlspecialchars((string) ($game['hearted_by'] ?: 'No hearts yet')) ?></span>
                        </p>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
