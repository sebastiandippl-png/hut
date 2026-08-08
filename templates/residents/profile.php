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
        <p>
            <strong>🎲 BGG user:</strong>
            <?php if (!empty($resident['bgg_username'])): ?>
                <a href="https://boardgamegeek.com/user/<?= rawurlencode((string) $resident['bgg_username']) ?>" target="_blank" rel="noopener noreferrer">
                    <?= htmlspecialchars((string) $resident['bgg_username']) ?>
                </a>
            <?php else: ?>
                Not mapped
            <?php endif; ?>
        </p>
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
            <h2>Games I shall pack <span class="count-badge"><?= count($gamesToPack) ?></span></h2>
            <p class="page-header__sub">Hut collection games where <?= htmlspecialchars($residentFirstName) ?> is the only tracked owner, or has claimed to bring it.</p>
            <?php if (empty($gamesToPack)): ?>
                <p class="empty-state">No games currently owned solely by this user in the hut collection.</p>
            <?php else: ?>
                <ul class="resident-list">
                    <?php foreach ($gamesToPack as $game): ?>
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
                                    <?php if (!empty($game['claimed_by_resident'])): ?>
                                        <span class="badge badge--success">Claimed to bring</span>
                                        <?php if ($isOwnProfile): ?>
                                            <form method="POST" action="/games/<?= (int) $game['id'] ?>/not-bring" style="display: inline;">
                                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                                                <button class="btn btn--small btn--warning" type="submit">I do not bring it</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="resident-profile__list-block">
            <h2>Not sure who brings it <span class="count-badge"><?= count($gamesUnclearOwner) ?></span></h2>
            <p class="page-header__sub">Hut collection games <?= htmlspecialchars($residentFirstName) ?> owns along with other tracked owners.</p>
            <?php if (empty($gamesUnclearOwner)): ?>
                <p class="empty-state">No shared-ownership games in the hut collection for this user.</p>
            <?php else: ?>
                <ul class="resident-list">
                    <?php foreach ($gamesUnclearOwner as $game): ?>
                        <?php $gameThumb = \Hut\BggThingFetcher::localUrl((int) $game['id']); ?>
                        <li>
                            <div class="resident-game-inline resident-game-inline--list">
                                <?php if ($gameThumb): ?>
                                    <a href="/games/<?= (int) $game['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <img class="resident-game-inline__thumb" src="<?= htmlspecialchars($gameThumb) ?>" alt="<?= htmlspecialchars((string) $game['name']) ?>" loading="lazy">
                                    </a>
                                <?php endif; ?>
                                <div>
                                    <a href="/games/<?= (int) $game['id'] ?>"><?= htmlspecialchars((string) $game['name']) ?></a><br>
                                    <small>Also owned by: <?= htmlspecialchars((string) $game['bgg_owned_by']) ?></small>
                                    <?php if (!empty($game['bringer_name'])): ?>
                                        <br><small>Already claimed by <?= htmlspecialchars((string) $game['bringer_name']) ?></small>
                                    <?php elseif ($isOwnProfile): ?>
                                        <br>
                                        <form method="POST" action="/games/<?= (int) $game['id'] ?>/bring" style="display: inline;">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                                            <button class="btn btn--small btn--success" type="submit">I bring it!</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="resident-profile__list-block">
            <h2>Games added by <?= htmlspecialchars($residentFirstName) ?> to the collection <span class="count-badge"><?= count($addedGames) ?></span></h2>
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
