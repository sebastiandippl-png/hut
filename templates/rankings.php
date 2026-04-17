<?php $pageTitle = 'Heart Rankings'; require __DIR__ . '/partials/header.php'; ?>

<div class="page-header">
    <h1>Heart Rankings</h1>
    <p class="page-header__sub">Ranked by group hearts</p>
</div>

<?php if (empty($rankings)): ?>
    <p class="empty-state">No hearts yet. <a href="/collection">Browse Hut Choice</a> and heart your favorites!</p>
<?php else: ?>
    <ol class="rankings">
        <?php foreach ($rankings as $i => $game): ?>
            <li class="rankings__item">
                <span class="rankings__rank">#<?= $i + 1 ?></span>
                <div class="rankings__info">
                    <a class="rankings__title" href="/games/<?= $game['id'] ?>">
                        <?= htmlspecialchars($game['name']) ?>
                    </a>
                    <?php if ($game['yearpublished']): ?>
                        <span class="rankings__year">(<?= $game['yearpublished'] ?>)</span>
                    <?php endif; ?>
                    <?php if ((int) ($game['rank'] ?? 0) > 0): ?>
                        <span class="rankings__year">BGG #<?= (int) $game['rank'] ?></span>
                    <?php endif; ?>
                    <div class="rankings__meta-group">
                        <span class="meta-row meta-row--owners">
                            <span class="meta-row__label">Owned by</span>
                            <span class="meta-row__value"><?= !empty($game['bgg_owned_by']) ? htmlspecialchars((string) $game['bgg_owned_by']) : 'No tracked owners' ?></span>
                        </span>
                        <span class="meta-row meta-row--hearts">
                            <span class="meta-row__label">Hearted by</span>
                            <span class="meta-row__value"><?= htmlspecialchars((string) ($game['hearted_by'] ?: 'No hearts yet')) ?></span>
                        </span>
                    </div>
                </div>
                <span class="rankings__tally tally--positive">
                    <?= (int) $game['hearts'] ?> ♥
                </span>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
