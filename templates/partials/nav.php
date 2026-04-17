<?php use Hut\Auth; $user = Auth::user(); ?>
<nav class="nav" id="mainNav">
    <a class="nav__brand" href="/games">🏠 Hut Game Manager</a>
    <button class="nav__burger" aria-label="Toggle menu" aria-expanded="false" aria-controls="mainNav" data-nav-burger>
        <span class="nav__burger-bar"></span>
        <span class="nav__burger-bar"></span>
        <span class="nav__burger-bar"></span>
    </button>
    <?php if ($user): ?>
        <div class="nav__links">
            <a href="/games" class="nav__link">Suggest</a>
            <a href="/collection" class="nav__link">Hut Menu</a>
            <a href="/rankings" class="nav__link">Hearts</a>
            <?php if ($user['is_admin']): ?>
                <a href="/admin" class="nav__link">Admin</a>
            <?php endif; ?>
        </div>
        <div class="nav__user">
            <span class="nav__username"><?= htmlspecialchars($user['name']) ?></span>
            <form method="POST" action="/logout" class="nav__logout-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
                <button type="submit" class="nav__link nav__link--logout nav__logout-button">Log out</button>
            </form>
        </div>
    <?php else: ?>
        <div class="nav__links">
            <a href="/login" class="nav__link">Log in</a>
            <a href="/register" class="nav__link nav__link--cta">Register</a>
        </div>
    <?php endif; ?>
</nav>
