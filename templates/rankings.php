<?php $pageTitle = 'Heart Rankings'; require __DIR__ . '/partials/header.php'; ?>

<div class="page-header">
    <h1>Heart Rankings</h1>
    <p class="page-header__sub">Ranked by group hearts</p>
</div>

<?php if (empty($rankings)): ?>
    <p class="empty-state">No hearts yet. <a href="/collection">Browse Hut Choice</a> and heart your favorites!</p>
<?php else: ?>
    <div class="game-grid">
        <?php foreach ($rankings as $i => $game): ?>
            <article class="game-card" data-id="<?= $game['id'] ?>" data-rank="<?= $i + 1 ?>">
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
                        <span title="Ranking">🏆 #<?= $i + 1 ?></span>
                        <?php if ((int) ($game['rank'] ?? 0) > 0): ?>
                            <span title="BGG rank">📊 <?= (int) $game['rank'] ?></span>
                        <?php endif; ?>
                        <span title="Hearts">💖 <?= (int) $game['hearts'] ?></span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
