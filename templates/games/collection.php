<?php
$pageTitle = 'Hut Choice';
require __DIR__ . '/../partials/header.php';

/**
 * Convert a comma-separated string of resident first names into HTML with
 * profile links. Names not found in $residentNameMap are left as plain text.
 */
function linkifyResidentNames(string $names, array $map): string
{
    if ($names === '' || $names === 'No hearts yet') {
        return htmlspecialchars($names);
    }
    $parts = explode(', ', $names);
    $linked = array_map(static function (string $name) use ($map): string {
        $name = trim($name);
        if (isset($map[$name])) {
            return '<a href="/residents/' . $map[$name] . '">' . htmlspecialchars($name) . '</a>';
        }
        return htmlspecialchars($name);
    }, $parts);
    return implode(', ', $linked);
}
?>

<div class="page-header">
    <h1>Hut Collection</h1>
    <p class="page-header__sub">This is the huts boardgame collection you chose. Give some hearts to games you want to play to make sure it'll be taken to the hut.</p>
</div>

<?php if (empty($games)): ?>
    <p class="empty-state">No games selected yet. <a href="/games">Suggest games</a> and add some!</p>
<?php else: ?>

    <div class="collection-filters">
        <div class="collection-filters__group">
            <span class="collection-filters__label">Best players</span>
            <input type="hidden" value="" data-collection-filter="bestplayers">
            <div class="filter-segs collection-filters__segs" data-collection-filter-group="bestplayers">
                <button class="filter-seg filter-seg--active" type="button" data-value="">Any</button>
                <button class="filter-seg" type="button" data-value="1">1</button>
                <button class="filter-seg" type="button" data-value="2">2</button>
                <button class="filter-seg" type="button" data-value="3">3</button>
                <button class="filter-seg" type="button" data-value="4">4</button>
                <button class="filter-seg" type="button" data-value="5">5</button>
                <button class="filter-seg" type="button" data-value="6">6+</button>
            </div>
        </div>

        <div class="collection-filters__group">
            <span class="collection-filters__label">Supports N+ players</span>
            <input type="hidden" value="" data-collection-filter="maxplayers">
            <div class="filter-segs collection-filters__segs" data-collection-filter-group="maxplayers">
                <button class="filter-seg filter-seg--active" type="button" data-value="">Any</button>
                <button class="filter-seg" type="button" data-value="2">2+</button>
                <button class="filter-seg" type="button" data-value="3">3+</button>
                <button class="filter-seg" type="button" data-value="4">4+</button>
                <button class="filter-seg" type="button" data-value="5">5+</button>
                <button class="filter-seg" type="button" data-value="6">6+</button>
            </div>
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
            <span class="collection-filters__label">Max playtime</span>
            <input type="hidden" value="" data-collection-filter="maxplaytime">
            <div class="filter-segs collection-filters__segs" data-collection-filter-group="maxplaytime">
                <button class="filter-seg filter-seg--active" type="button" data-value="">Any</button>
                <button class="filter-seg" type="button" data-value="0-30">0–30 min</button>
                <button class="filter-seg" type="button" data-value="31-60">31–60 min</button>
                <button class="filter-seg" type="button" data-value="61-90">61–90 min</button>
                <button class="filter-seg" type="button" data-value="91-120">91–120 min</button>
                <button class="filter-seg" type="button" data-value="121+">&gt; 120 min</button>
            </div>
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
                } elseif ($complexity <= 3.0) {
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
                     data-hearts="<?= $hearts ?>"
                     data-rank="<?= isset($game['rank']) && (int) $game['rank'] > 0 ? (int) $game['rank'] : -1 ?>"
                     data-categories="<?= htmlspecialchars((string) ($game['categories'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                     data-mechanics="<?= htmlspecialchars((string) ($game['mechanics'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                     data-designers="<?= htmlspecialchars((string) ($game['designers'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

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
                            <span class="meta-row__icon" title="Owned by">🏠</span>
                            <span class="meta-row__value"><?= !empty($game['bgg_owned_by']) ? htmlspecialchars((string) $game['bgg_owned_by']) : 'No tracked owners' ?></span>
                        </p>
                        <p class="meta-row meta-row--hearts">
                            <span class="meta-row__icon" title="Hearted by">♥</span>
                            <span class="meta-row__value" data-hearted-by="<?= $game['id'] ?>" data-hearted-mode="names"><?= linkifyResidentNames((string) ($game['hearted_by'] ?: 'No hearts yet'), $residentNameMap) ?></span>
                        </p>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<script>window.HUT_RESIDENT_MAP = <?= json_encode($residentNameMap, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
