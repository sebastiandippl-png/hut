<?php
use Hut\Auth;

$user = Auth::user();
$currentPath = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';

$isGamesSuggest = $currentPath === '/' || $currentPath === '/games';
$isGamesCollection = $currentPath === '/collection';
$isGamesRanking = $currentPath === '/rankings';
$isGamesGroupActive = $isGamesSuggest || $isGamesCollection || $isGamesRanking || str_starts_with($currentPath, '/games/');

$isAdminUsers = $currentPath === '/admin' || $currentPath === '/admin/users';
$isAdminImport = $currentPath === '/admin/import';
$isAdminNotice = $currentPath === '/admin/notice';
$isAdminGroupActive = str_starts_with($currentPath, '/admin');

$gamesGroupClass = 'nav__group' . ($isGamesGroupActive ? ' nav__group--active' : '');
$adminGroupClass = 'nav__group' . ($isAdminGroupActive ? ' nav__group--active' : '');
?>
<nav class="nav" id="mainNav">
    <a class="nav__brand" href="/games">🏠 Hut Game Manager</a>
    <button class="nav__burger" aria-label="Toggle menu" aria-expanded="false" aria-controls="mainNav" data-nav-burger>
        <span class="nav__burger-bar"></span>
        <span class="nav__burger-bar"></span>
        <span class="nav__burger-bar"></span>
    </button>
    <?php if ($user): ?>
        <div class="nav__items">
            <div class="<?= htmlspecialchars($gamesGroupClass) ?>" data-nav-group>
                <button type="button" class="nav__group-btn" data-nav-group-toggle aria-expanded="false" aria-haspopup="true">
                    Games
                    <span class="nav__arrow" aria-hidden="true">▾</span>
                </button>
                <div class="nav__dropdown">
                    <a href="/games" class="nav__dropdown-link<?= $isGamesSuggest ? ' nav__dropdown-link--active' : '' ?>">Suggest</a>
                    <a href="/collection" class="nav__dropdown-link<?= $isGamesCollection ? ' nav__dropdown-link--active' : '' ?>">Hut Collection</a>
                    <a href="/rankings" class="nav__dropdown-link<?= $isGamesRanking ? ' nav__dropdown-link--active' : '' ?>">Heart Ranking</a>
                </div>
            </div>
            <?php if ($user['is_admin']): ?>
                <div class="<?= htmlspecialchars($adminGroupClass) ?>" data-nav-group>
                    <button type="button" class="nav__group-btn" data-nav-group-toggle aria-expanded="false" aria-haspopup="true">
                        Admin
                        <span class="nav__arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="nav__dropdown">
                        <a href="/admin/users" class="nav__dropdown-link<?= $isAdminUsers ? ' nav__dropdown-link--active' : '' ?>">Users</a>
                        <a href="/admin/import" class="nav__dropdown-link<?= $isAdminImport ? ' nav__dropdown-link--active' : '' ?>">BGG Data</a>
                        <a href="/admin/notice" class="nav__dropdown-link<?= $isAdminNotice ? ' nav__dropdown-link--active' : '' ?>">Notification Banner</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="nav__right">
            <span class="nav__username"><?= htmlspecialchars(Auth::firstName($user['name'])) ?></span>
            <form method="POST" action="/logout" class="nav__logout-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                <button type="submit" class="nav__logout-button">Log out</button>
            </form>
        </div>
    <?php else: ?>
        <div class="nav__items nav__items--guest">
            <a href="/login" class="nav__guest-link">Log in</a>
            <a href="/register" class="nav__guest-link nav__guest-link--cta">Register</a>
        </div>
    <?php endif; ?>
</nav>
