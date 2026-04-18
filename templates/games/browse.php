<?php $pageTitle = 'Suggest Games'; require __DIR__ . '/../partials/header.php'; ?>

<div class="page-header">
    <h1>Suggest Games</h1>
    <p class="page-header__sub">Get inspired by a random choice of games that are owned by a fellow hut lover. Or search across BoardGameGeek to find a game for the menu…</p>
</div>

<form class="filters" method="GET" action="/games" data-browse-filters>
    <div class="search-autocomplete" data-autocomplete>
        <input class="form__input filters__search" type="search" name="q" placeholder="Search by game name…"
               value="<?= htmlspecialchars($search) ?>" autocomplete="off" spellcheck="false"
               data-autocomplete-input aria-autocomplete="list" aria-expanded="false" aria-haspopup="listbox">
        <div class="search-autocomplete__menu" data-autocomplete-menu hidden></div>
    </div>

    <?php if (!empty($availableUsers)): ?>
        <div class="filters__users-group">
            <span class="filters__label">In Collection of</span>
            <div class="filters__pills">
                <?php foreach ($availableUsers as $filterUser): ?>
                    <label class="filters__pill">
                        <input type="checkbox" name="users[]" value="<?= htmlspecialchars($filterUser) ?>" <?= in_array($filterUser, $selectedUsers, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($filterUser) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <button type="submit" hidden aria-hidden="true"></button>
    <a class="btn btn--ghost" href="/games">Reset</a>
</form>

<?php if (empty($games)): ?>
    <p class="empty-state">No games found. <?php if (!\Hut\Auth::user()['is_admin']): ?>Ask an admin to import a BGG CSV export.<?php endif; ?></p>
<?php else: ?>
    <p class="results-count"><?= $total ?> game<?= $total !== 1 ? 's' : '' ?></p>

    <div class="game-grid">
        <?php foreach ($games as $game): ?>
            <?php
            $isSelectedByMe = (int) ($game['selected_by_me'] ?? 0) === 1;
            $isInHut = (int) ($game['in_hut'] ?? 0) === 1;
            $rank = (int) ($game['rank'] ?? 0);
            ?>
            <article class="game-card" data-id="<?= $game['id'] ?>">
                <?php $thumbUrl = \Hut\BggThingFetcher::localUrl($game['id']); ?>
                <?php if ($thumbUrl): ?>
                    <a href="/games/<?= $game['id'] ?>">
                        <img class="game-card__thumb"
                             src="<?= htmlspecialchars($thumbUrl) ?>"
                             alt="<?= htmlspecialchars($game['name']) ?>"
                             loading="lazy">
                    </a>
                <?php else: ?>
                    <a href="/games/<?= $game['id'] ?>" class="game-card__thumb-placeholder"><?= $rank > 0 ? '#' . htmlspecialchars((string) $rank) : 'BGG' ?></a>
                <?php endif; ?>

                <div class="game-card__body">
                    <h2 class="game-card__title">
                        <a href="/games/<?= $game['id'] ?>"><?= htmlspecialchars($game['name']) ?></a>
                        <?php if ($game['yearpublished']): ?>
                            <span class="game-card__year">(<?= $game['yearpublished'] ?>)</span>
                        <?php endif; ?>
                    </h2>

                    <div class="game-card__meta">
                        <?php if ($rank > 0): ?>
                            <span title="BGG rank">🏆 #<?= $rank ?></span>
                        <?php endif; ?>
                        <?php if ($game['bayesaverage']): ?>
                            <span title="Bayes average">⭐ <?= number_format((float) $game['bayesaverage'], 2) ?></span>
                        <?php endif; ?>
                        <?php if ($game['usersrated']): ?>
                            <span title="Users rated">🗳 <?= number_format((int) $game['usersrated']) ?></span>
                        <?php endif; ?>
                        <?php if ((int) ($game['is_expansion'] ?? 0) === 1): ?>
                            <span title="Expansion">🧩 Expansion</span>
                        <?php endif; ?>
                        <?php if (!empty($game['collection_owners'])): ?>
                            <span title="In collection of">📦 <?= htmlspecialchars($game['collection_owners']) ?></span>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn--select <?= $isInHut ? 'btn--select--active' : '' ?>"
                            data-game-id="<?= $game['id'] ?>"
                            title="<?= $isSelectedByMe ? 'Remove your hut selection' : ($isInHut ? 'In hut (added by another user)' : 'Add to hut') ?>"
                            <?= $isInHut && !$isSelectedByMe ? 'disabled' : '' ?>>
                        <?= $isSelectedByMe ? '− Remove from hut' : ($isInHut ? '🏠 In hut' : '+ Add to hut') ?>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php
    $pages = (int) ceil($total / $perPage);
    if ($pages > 1):
        $base = '/games?' . http_build_query(array_filter([
            'q' => $search,
            'users' => !empty($selectedUsers) ? $selectedUsers : null,
        ], static fn ($value) => $value !== null && $value !== ''));
    ?>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a class="pagination__link" href="<?= $base ?>&page=<?= $page - 1 ?>">← Prev</a>
            <?php endif; ?>
            <span class="pagination__info"><?= $page ?> / <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="pagination__link" href="<?= $base ?>&page=<?= $page + 1 ?>">Next →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
