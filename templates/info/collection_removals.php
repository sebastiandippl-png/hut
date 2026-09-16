<?php $pageTitle = 'Collection Removals'; require __DIR__ . '/../partials/header.php'; ?>

<div class="page-header">
    <h1>Collection Removals</h1>
    <p class="page-header__sub">Log of games removed from the hut collection, newest first.</p>
</div>

<section class="changelog" aria-label="Collection removal history">
    <?php if (empty($removals)): ?>
        <div class="card">
            <p class="empty-state">No games have been removed from the hut collection yet.</p>
        </div>
    <?php else: ?>
        <?php $reasonLabels = ['exclusive_owner' => 'Sole owner', 'suggester' => 'Original adder']; ?>
        <ol class="changelog__list">
            <?php foreach ($removals as $removal): ?>
                <li class="changelog__entry">
                    <article class="changelog__card">
                        <header class="changelog__entry-header">
                            <span class="changelog__author"><?= htmlspecialchars((string) $removal['removed_by_name']) ?></span>
                            <span class="changelog__date"><?= htmlspecialchars((string) $removal['removed_at']) ?></span>
                        </header>
                        <p class="changelog__subject">
                            Removed <a href="/games/<?= (int) $removal['game_id'] ?>"><?= htmlspecialchars((string) $removal['game_name']) ?></a>
                            <span class="badge"><?= htmlspecialchars($reasonLabels[$removal['reason']] ?? (string) $removal['reason']) ?></span>
                        </p>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
