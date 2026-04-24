<?php
$residentName = (string) ($resident['name'] ?? 'Resident');
$residentFirstName = \Hut\Auth::firstName($residentName);
$pageTitle = 'Resident Profile - ' . $residentName;
require __DIR__ . '/../partials/header.php';
?>

<div class="page-header">
    <h1>👤 <?= htmlspecialchars($residentName) ?></h1>
    <p class="page-header__sub">🏠 Hut resident profile and game activity</p>
</div>

<section class="card resident-profile">
    <div class="resident-profile__meta-top">
        <p><strong>🏡 Registered:</strong> <?= !empty($resident['created_at']) ? htmlspecialchars((string) $resident['created_at']) : 'Unknown' ?></p>
        <p><strong>🕒 Last login:</strong> <?= !empty($resident['last_login_at']) ? htmlspecialchars((string) $resident['last_login_at']) : 'Never logged in' ?></p>
    </div>

    <div class="resident-profile__latest-grid">
        <div class="resident-profile__latest-item">
            <h2>♥ Latest game hearted by <?= htmlspecialchars($residentFirstName) ?></h2>
            <?php if ($latestHearted): ?>
                <?php $latestHeartedThumb = \Hut\BggThingFetcher::localUrl((int) $latestHearted['id']); ?>
                <div class="resident-game-inline">
                    <?php if ($latestHeartedThumb): ?>
                        <a href="/games/<?= (int) $latestHearted['id'] ?>" tabindex="-1" aria-hidden="true">
                            <img class="resident-game-inline__thumb" src="<?= htmlspecialchars($latestHeartedThumb) ?>" alt="<?= htmlspecialchars((string) $latestHearted['name']) ?>" loading="lazy">
                        </a>
                    <?php endif; ?>
                    <p>
                        <a href="/games/<?= (int) $latestHearted['id'] ?>"><?= htmlspecialchars((string) $latestHearted['name']) ?></a><br>
                        <small><?= htmlspecialchars((string) $latestHearted['hearted_at']) ?></small>
                    </p>
                </div>
            <?php else: ?>
                <p class="empty-state">No hearted games yet.</p>
            <?php endif; ?>
        </div>

        <div class="resident-profile__latest-item">
            <h2>➕ Latest game added by <?= htmlspecialchars($residentFirstName) ?> to the hut collection</h2>
            <?php if ($latestAdded): ?>
                <?php $latestAddedThumb = \Hut\BggThingFetcher::localUrl((int) $latestAdded['id']); ?>
                <div class="resident-game-inline">
                    <?php if ($latestAddedThumb): ?>
                        <a href="/games/<?= (int) $latestAdded['id'] ?>" tabindex="-1" aria-hidden="true">
                            <img class="resident-game-inline__thumb" src="<?= htmlspecialchars($latestAddedThumb) ?>" alt="<?= htmlspecialchars((string) $latestAdded['name']) ?>" loading="lazy">
                        </a>
                    <?php endif; ?>
                    <p>
                        <a href="/games/<?= (int) $latestAdded['id'] ?>"><?= htmlspecialchars((string) $latestAdded['name']) ?></a><br>
                        <small><?= htmlspecialchars((string) $latestAdded['added_at']) ?></small>
                    </p>
                </div>
            <?php else: ?>
                <p class="empty-state">No games added to the hut collection yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="resident-profile__lists">
        <section class="resident-profile__list-block">
            <h2>🎲 Games added by <?= htmlspecialchars($residentFirstName) ?> to the collection <span class="count-badge"><?= count($addedGames) ?></span></h2>
            <?php if (empty($addedGames)): ?>
                <p class="empty-state">No games currently added by this user.</p>
            <?php else: ?>
                <ul class="resident-list">
                    <?php foreach ($addedGames as $game): ?>
                        <?php $gameThumb = \Hut\BggThingFetcher::localUrl((int) $game['id']); ?>
                        <li>
                            <div class="resident-game-inline resident-game-inline--list">
                                <?php if ($gameThumb): ?>
                                    <a href="/games/<?= (int) $game['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <img class="resident-game-inline__thumb" src="<?= htmlspecialchars($gameThumb) ?>" alt="<?= htmlspecialchars((string) $game['name']) ?>" loading="lazy">
                                    </a>
                                <?php endif; ?>
                                <div>
                                    <a href="/games/<?= (int) $game['id'] ?>"><?= htmlspecialchars((string) $game['name']) ?></a>
                                    <small><?= htmlspecialchars((string) $game['added_at']) ?></small>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="resident-profile__list-block">
            <h2>💖 Games hearted by <?= htmlspecialchars($residentFirstName) ?> <span class="count-badge"><?= count($heartedGames) ?></span></h2>
            <?php if (empty($heartedGames)): ?>
                <p class="empty-state">No hearted games yet.</p>
            <?php else: ?>
                <ul class="resident-list">
                    <?php foreach ($heartedGames as $game): ?>
                        <?php $gameThumb = \Hut\BggThingFetcher::localUrl((int) $game['id']); ?>
                        <li>
                            <div class="resident-game-inline resident-game-inline--list">
                                <?php if ($gameThumb): ?>
                                    <a href="/games/<?= (int) $game['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <img class="resident-game-inline__thumb" src="<?= htmlspecialchars($gameThumb) ?>" alt="<?= htmlspecialchars((string) $game['name']) ?>" loading="lazy">
                                    </a>
                                <?php endif; ?>
                                <div>
                                    <a href="/games/<?= (int) $game['id'] ?>"><?= htmlspecialchars((string) $game['name']) ?></a>
                                    <small><?= htmlspecialchars((string) $game['hearted_at']) ?></small>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
