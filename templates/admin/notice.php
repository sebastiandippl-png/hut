<?php
$pageTitle = 'Admin Notification Banner';
require __DIR__ . '/../partials/header.php';
?>

<div class="page-header">
    <h1>Notification Banner</h1>
    <p class="page-header__sub">Show, hide, and edit the global message displayed at the top of each page</p>
</div>

<section class="card admin-users">
    <h2>Top Notification Banner</h2>
    <form method="POST" action="/admin/site-notice" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">

        <label class="form__label" for="site_notice_enabled" style="display:flex; align-items:center; gap:.5rem;">
            <input type="checkbox" id="site_notice_enabled" name="site_notice_enabled" value="1" <?= !empty($siteNotice['enabled']) ? 'checked' : '' ?>>
            Display notification banner
        </label>

        <label class="form__label" for="site_notice_message">Notification message</label>
        <textarea class="form__input" id="site_notice_message" name="site_notice_message" rows="4" maxlength="2000"><?= htmlspecialchars((string) ($siteNotice['message'] ?? '')) ?></textarea>

        <button class="btn btn--primary" type="submit">Save notification</button>
    </form>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
