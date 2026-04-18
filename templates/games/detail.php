<?php $pageTitle = htmlspecialchars($game['name']); require __DIR__ . '/../partials/header.php'; ?>

<?php $rank = (int) ($game['rank'] ?? 0); ?>
<?php $thumbUrl = \Hut\BggThingFetcher::localUrl($game['id']); ?>
<?php
$thingName = trim((string) ($game['thing_primary_name'] ?? ''));
$thingDescription = trim((string) ($game['thing_description'] ?? ''));
$thingYear = (int) ($game['thing_yearpublished'] ?? 0);
$thingMinPlayers = (int) ($game['thing_minplayers'] ?? 0);
$thingMaxPlayers = (int) ($game['thing_maxplayers'] ?? 0);
$thingMinAge = (int) ($game['thing_minage'] ?? 0);
$thingMinPlaytime = (int) ($game['thing_minplaytime'] ?? 0);
$thingMaxPlaytime = (int) ($game['thing_maxplaytime'] ?? 0);

$thingCategories = array_values(array_filter(array_map('trim', explode(',', (string) ($game['thing_categories'] ?? '')))));
$thingMechanics = array_values(array_filter(array_map('trim', explode(',', (string) ($game['thing_mechanics'] ?? '')))));
$thingDesigners = array_values(array_filter(array_map('trim', explode(',', (string) ($game['thing_designers'] ?? '')))));
$thingPublishers = array_values(array_filter(array_map('trim', explode(',', (string) ($game['thing_publishers'] ?? '')))));
$thingFamilies = array_values(array_filter(array_map('trim', explode(',', (string) ($game['thing_families'] ?? '')))));
$thingBestPlayerCount = trim((string) ($game['thing_best_playercount'] ?? ''));

$parsePollPairs = static function (?string $raw): array {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return [];
    }

    $pairs = [];
    foreach (explode('|', $raw) as $chunk) {
        $parts = explode(':', trim($chunk), 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = (int) trim($parts[1]);
        if ($key !== '') {
            $pairs[] = ['label' => $key, 'votes' => $value];
        }
    }

    return $pairs;
};

$thingBestVotes = $parsePollPairs($game['thing_player_best_values'] ?? null);
$thingRecommendedVotes = $parsePollPairs($game['thing_player_recommended_values'] ?? null);
$thingNotRecommendedVotes = $parsePollPairs($game['thing_player_not_recommended_values'] ?? null);

$thingUpdatedRaw = trim((string) ($game['thing_last_updated'] ?? ''));
$thingUpdated = null;
if ($thingUpdatedRaw !== '') {
    $timestamp = strtotime($thingUpdatedRaw);
    if ($timestamp !== false) {
        $thingUpdated = date('Y-m-d H:i', $timestamp);
    }
}

$detailFacts = [];
if ($thingMinPlayers > 0 && $thingMaxPlayers > 0) {
    $detailFacts['Players'] = $thingMinPlayers === $thingMaxPlayers
        ? (string) $thingMinPlayers
        : $thingMinPlayers . '-' . $thingMaxPlayers;
}
if ($thingMinPlaytime > 0 && $thingMaxPlaytime > 0) {
    $detailFacts['Playtime'] = $thingMinPlaytime === $thingMaxPlaytime
        ? $thingMinPlaytime . ' min'
        : $thingMinPlaytime . '-' . $thingMaxPlaytime . ' min';
}
if ($thingMinAge > 0) {
    $detailFacts['Minimum age'] = $thingMinAge . '+';
}
if (!empty($game['thing_averageweight'])) {
    $detailFacts['Complexity'] = number_format((float) $game['thing_averageweight'], 2) . '/5';
}
if (!empty($game['thing_owned'])) {
    $detailFacts['Owned on BGG'] = number_format((int) $game['thing_owned']);
}
if (!empty($game['thing_usersrated'])) {
    $detailFacts['Users rated'] = number_format((int) $game['thing_usersrated']);
}
if ($thingBestPlayerCount !== '') {
    $detailFacts['Best player count'] = $thingBestPlayerCount;
}

$collectionOwners = trim((string) ($game['bgg_owned_by'] ?? ''));
?>

<div class="detail">
    <div class="detail__media">
        <?php if ($thumbUrl): ?>
            <img class="detail__image"
                 src="<?= htmlspecialchars($thumbUrl) ?>"
                 alt="<?= htmlspecialchars($game['name']) ?>"
                 loading="lazy">
        <?php else: ?>
            <div class="detail__image-placeholder"><?= $rank > 0 ? '#' . htmlspecialchars((string) $rank) : 'BGG' ?></div>
        <?php endif; ?>
    </div>

    <div class="detail__info">
        <h1 class="detail__title">
            <?= htmlspecialchars($thingName !== '' ? $thingName : (string) $game['name']) ?>
            <?php if ($thingYear > 0 || !empty($game['yearpublished'])): ?>
                <span class="detail__year">(<?= (int) ($thingYear > 0 ? $thingYear : $game['yearpublished']) ?>)</span>
            <?php endif; ?>
        </h1>

        <div class="detail__actions">
            <button class="btn btn--select <?= $isInHut ? 'btn--select--active' : '' ?>"
                    data-game-id="<?= $game['id'] ?>"
                    title="<?= $isSelectedByMe ? 'Remove your hut selection' : ($isInHut ? 'In hut (added by another user)' : 'Add to hut') ?>"
                    <?= $isInHut && !$isSelectedByMe ? 'disabled' : '' ?>>
                <?= $isSelectedByMe ? '− Remove from hut' : ($isInHut ? '🏠 In hut' : '+ Add to hut') ?>
            </button>

            <?php if (!empty(\Hut\Auth::user()['is_admin']) && $isInHut): ?>
                <form method="POST" action="/admin/games/<?= (int) $game['id'] ?>/remove-from-hut">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                    <button class="btn btn--danger" type="submit">Remove From Hut Menu (All Users)</button>
                </form>
            <?php endif; ?>

            <div class="heart-controls" data-game-id="<?= $game['id'] ?>">
                <button class="btn btn--heart <?= $hearted ? 'btn--heart--active' : '' ?>"
                        data-heart-button
                        data-game-id="<?= $game['id'] ?>">
                    <?= $hearted ? '♥ Hearted' : '♥ Heart' ?>
                </button>
                <span class="heart-summary"><span class="heart-tally"><?= $hearts ?></span> <span class="heart-label">heart<?= $hearts !== 1 ? 's' : '' ?></span></span>
            </div>

            <span class="game-card__selectors" data-hearted-by="<?= $game['id'] ?>">Hearted by: <?= htmlspecialchars($heartedBy) ?></span>
            <span class="game-card__selectors">In collections: <?= $collectionOwners !== '' ? htmlspecialchars($collectionOwners) : 'No tracked owners yet' ?></span>

            <?php if (!empty($isInMyCollection)): ?>
                <form method="POST" action="/games/<?= (int) $game['id'] ?>/remove-from-collection">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                    <button class="btn btn--ghost" type="submit">− Remove from my collection</button>
                </form>
            <?php elseif ($collectionOwners === ''): ?>
                <form method="POST" action="/games/<?= (int) $game['id'] ?>/add-to-collection">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                    <button class="btn btn--ghost" type="submit">+ Add to my collection</button>
                </form>
            <?php endif; ?>

            <a class="btn btn--ghost"
                    href="https://boardgamegeek.com/boardgame/<?= $game['id'] ?>"
               target="_blank" rel="noopener">View on BGG ↗</a>
        </div>

        <div class="detail__meta-row">
            <?php if ($rank > 0): ?>
                <span>🏆 BGG rank #<?= $rank ?></span>
            <?php endif; ?>
            <?php if ($game['bayesaverage']): ?>
                <span>⭐ Bayes average: <?= number_format((float) $game['bayesaverage'], 5) ?></span>
            <?php endif; ?>
            <?php if ($game['average']): ?>
                <span>📈 Average rating: <?= number_format((float) $game['average'], 5) ?></span>
            <?php endif; ?>
            <?php if ($game['usersrated']): ?>
                <span>🗳 Users rated: <?= number_format((int) $game['usersrated']) ?></span>
            <?php endif; ?>
            <?php if ((int) ($game['is_expansion'] ?? 0) === 1): ?>
                <span>🧩 Expansion</span>
            <?php endif; ?>
            <?php if (!empty($game['thing_last_updated'])): ?>
                <span>🔄 Thing data: fresh</span>
            <?php endif; ?>
        </div>

        <?php if ($detailFacts): ?>
            <p class="detail__section-label">From BGG thing</p>
            <div class="detail__fact-grid">
                <?php foreach ($detailFacts as $label => $value): ?>
                    <div class="detail__fact-card">
                        <span class="detail__fact-label"><?= htmlspecialchars($label) ?></span>
                        <span class="detail__fact-value"><?= htmlspecialchars($value) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        $categoryRanks = [
            'Abstract' => $game['abstracts_rank'],
            'CGS' => $game['cgs_rank'],
            'Children' => $game['childrensgames_rank'],
            'Family' => $game['familygames_rank'],
            'Party' => $game['partygames_rank'],
            'Strategy' => $game['strategygames_rank'],
            'Thematic' => $game['thematic_rank'],
            'Wargame' => $game['wargames_rank'],
        ];
        $categoryRanks = array_filter($categoryRanks, static fn ($value) => (int) $value > 0);
        ?>

        <?php if ($thingBestVotes || $thingRecommendedVotes || $thingNotRecommendedVotes): ?>
            <p class="detail__section-label">Suggested players poll</p>
            <?php
            $allLabels = [];
            foreach ([$thingBestVotes, $thingRecommendedVotes, $thingNotRecommendedVotes] as $set) {
                foreach ($set as $pair) { $allLabels[$pair['label']] = true; }
            }
            $indexByLabel = static function (array $votes): array {
                $idx = [];
                foreach ($votes as $pair) { $idx[$pair['label']] = $pair['votes']; }
                return $idx;
            };
            $bestIdx = $indexByLabel($thingBestVotes);
            $recIdx  = $indexByLabel($thingRecommendedVotes);
            $notIdx  = $indexByLabel($thingNotRecommendedVotes);
            ?>
            <div class="poll-table">
                <div class="poll-table__head">
                    <span>Players</span>
                    <span class="poll-table__num--best">Best</span>
                    <span class="poll-table__num--rec">Rec.</span>
                    <span class="poll-table__num--not">Not</span>
                    <span class="poll-table__bar-col">Vote split</span>
                </div>
                <?php foreach (array_keys($allLabels) as $lbl): ?>
                    <?php
                    $b = $bestIdx[$lbl] ?? 0;
                    $r = $recIdx[$lbl]  ?? 0;
                    $n = $notIdx[$lbl]  ?? 0;
                    $total = $b + $r + $n;
                    $pctB = $total > 0 ? round($b / $total * 100) : 0;
                    $pctR = $total > 0 ? round($r / $total * 100) : 0;
                    $pctN = $total > 0 ? 100 - $pctB - $pctR : 0;
                    $isBest = ($b > 0 && $b >= $r && $b >= $n);
                    ?>
                    <div class="poll-table__row <?= $isBest ? 'poll-table__row--best' : '' ?>">
                        <span class="poll-table__label"><?= htmlspecialchars($lbl) ?><?= $isBest ? ' ★' : '' ?></span>
                        <span class="poll-table__num poll-table__num--best"><?= $b ?></span>
                        <span class="poll-table__num poll-table__num--rec"><?= $r ?></span>
                        <span class="poll-table__num poll-table__num--not"><?= $n ?></span>
                        <div class="poll-bar">
                            <?php if ($pctB > 0): ?><div class="poll-bar__seg poll-bar__seg--best" style="width:<?= $pctB ?>%" title="Best: <?= $b ?>"></div><?php endif; ?>
                            <?php if ($pctR > 0): ?><div class="poll-bar__seg poll-bar__seg--rec"  style="width:<?= $pctR ?>%" title="Recommended: <?= $r ?>"></div><?php endif; ?>
                            <?php if ($pctN > 0): ?><div class="poll-bar__seg poll-bar__seg--not"  style="width:<?= $pctN ?>%" title="Not recommended: <?= $n ?>"></div><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($thingUpdated): ?>
            <p class="detail__updated">Last updated from BGG: <?= htmlspecialchars($thingUpdated) ?></p>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
