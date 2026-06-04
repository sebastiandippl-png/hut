<?php

declare(strict_types=1);

use Hut\BggThingFetcher;

$pageTitle = 'Who Brings It';
require __DIR__ . '/../partials/header.php';
?>

<div class="page-header">
    <h1>Who Brings It</h1>
    <p class="page-header__sub">Games from the hut collection where bringing responsibility is unclear: nobody tracked, or multiple tracked owners.</p>
</div>

<?php if (empty($games)): ?>
    <p class="empty-state">All selected games currently have exactly one tracked owner.</p>
<?php else: ?>
    <p class="results-count"><?= count($games) ?> game<?= count($games) !== 1 ? 's' : '' ?> need coordination</p>

    <div class="game-grid">
        <?php foreach ($games as $game): ?>
            <?php
            $thumbUrl = BggThingFetcher::localUrl((int) $game['id']);
            $rank = (int) ($game['rank'] ?? 0);
            $ownerCount = (int) ($game['owner_count'] ?? 0);
            $owners = trim((string) ($game['bgg_owned_by'] ?? ''));
            ?>
            <article class="game-card">
                <?php if ($thumbUrl): ?>
                    <a href="/games/<?= (int) $game['id'] ?>">
                        <img class="game-card__thumb"
                             src="<?= htmlspecialchars($thumbUrl) ?>"
                             alt="<?= htmlspecialchars((string) $game['name']) ?>"
                             loading="lazy">
                    </a>
                <?php else: ?>
                    <a href="/games/<?= (int) $game['id'] ?>" class="game-card__thumb-placeholder"><?= $rank > 0 ? '#' . htmlspecialchars((string) $rank) : 'BGG' ?></a>
                <?php endif; ?>

                <div class="game-card__body">
                    <h2 class="game-card__title">
                        <a href="/games/<?= (int) $game['id'] ?>"><?= htmlspecialchars((string) $game['name']) ?></a>
                        <?php if (!empty($game['yearpublished'])): ?>
                            <span class="game-card__year">(<?= (int) $game['yearpublished'] ?>)</span>
                        <?php endif; ?>
                    </h2>

                    <div class="game-card__meta">
                        <?php if ($rank > 0): ?>
                            <span title="BGG rank">🏆 #<?= $rank ?></span>
                        <?php endif; ?>
                        <span title="Tracked owners">📦 <?= $ownerCount ?> owner<?= $ownerCount !== 1 ? 's' : '' ?></span>
                    </div>

                    <p class="collection-card__meta" style="margin-top: 0.6rem;">
                        <?php if ($ownerCount === 0): ?>
                            No tracked owner yet.
                        <?php else: ?>
                            Owned by <?= htmlspecialchars($owners) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
