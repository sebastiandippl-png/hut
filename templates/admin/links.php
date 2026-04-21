<?php
$pageTitle = 'Admin Links';
require __DIR__ . '/../partials/header.php';
?>

<div class="page-header">
    <h1>Links</h1>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="flash" role="alert"><?= htmlspecialchars((string) $_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="flash flash--error" role="alert"><?= htmlspecialchars((string) $_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<section class="card">
    <h2>Add Link</h2>
    <form method="POST" action="/admin/links" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">

        <label class="form__label" for="link_url">URL <span aria-hidden="true">*</span></label>
        <input class="form__input" type="url" id="link_url" name="url" required placeholder="https://example.com">

        <label class="form__label" for="link_title">Title <span aria-hidden="true">*</span></label>
        <input class="form__input" type="text" id="link_title" name="title" required maxlength="255" placeholder="A great resource">

        <label class="form__label" for="link_description">Description</label>
        <input class="form__input" type="text" id="link_description" name="description" maxlength="500" placeholder="Short description (optional)">

        <label class="form__label" for="link_sort_order">Sort order</label>
        <input class="form__input" type="number" id="link_sort_order" name="sort_order" value="0" min="0" max="9999">
        <p class="form__hint">Lower numbers appear first. Ties are broken by newest first.</p>

        <button class="btn btn--primary" type="submit">Add link</button>
    </form>
</section>

<section class="card">
    <h2>Existing Links</h2>
    <?php if (empty($links)): ?>
        <p class="empty-state">No links yet. Add one above.</p>
    <?php else: ?>
        <div class="admin-links__list">
            <?php foreach ($links as $link): ?>
                <div class="admin-links__item">
                    <div class="admin-links__info">
                        <a class="admin-links__title" href="<?= htmlspecialchars((string) $link['url']) ?>" target="_blank" rel="noopener noreferrer">
                            <?= htmlspecialchars((string) $link['title']) ?>
                        </a>
                        <?php if (!empty($link['description'])): ?>
                            <span class="admin-links__desc"><?= htmlspecialchars((string) $link['description']) ?></span>
                        <?php endif; ?>
                        <span class="admin-links__url tag tag--muted"><?= htmlspecialchars((string) $link['url']) ?></span>
                    </div>
                    <form method="POST" action="/admin/links/<?= (int) $link['id'] ?>/delete" class="admin-links__delete-form">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                        <button type="submit" class="btn btn--ghost" onclick="return confirm('Delete this link?')">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
