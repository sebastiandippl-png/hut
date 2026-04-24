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

        <label class="form__label" for="link_category">Category</label>
        <select class="form__input" id="link_category" name="category_id">
            <option value="">Uncategorized</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars((string) $category['name']) ?></option>
            <?php endforeach; ?>
        </select>

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
        <div class="admin-links__toolbar">
            <form method="POST" action="/admin/links/refetch-all-previews">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                <button type="submit" class="btn btn--ghost">↺ Refetch all previews</button>
            </form>
        </div>
        <div class="admin-links__list">
            <?php foreach ($links as $link): ?>
                <div class="admin-links__item">
                    <?php if (!empty($link['preview_image_url'])): ?>
                        <img class="admin-links__preview" src="<?= htmlspecialchars((string) $link['preview_image_url']) ?>" alt="" loading="lazy">
                    <?php endif; ?>
                    <div class="admin-links__info">
                        <a class="admin-links__title" href="<?= htmlspecialchars((string) $link['url']) ?>" target="_blank" rel="noopener noreferrer">
                            <?= htmlspecialchars((string) $link['title']) ?>
                        </a>
                        <?php if (!empty($link['description'])): ?>
                            <span class="admin-links__desc"><?= htmlspecialchars((string) $link['description']) ?></span>
                        <?php endif; ?>
                        <span class="tag tag--muted">category: <?= htmlspecialchars((string) ($link['category_name'] ?? 'Uncategorized')) ?></span>
                        <span class="admin-links__url tag tag--muted"><?= htmlspecialchars((string) $link['url']) ?></span>
                        <?php if ($link['preview_image_url'] === null): ?>
                            <span class="tag tag--muted">preview: not fetched</span>
                        <?php elseif ($link['preview_image_url'] === ''): ?>
                            <span class="tag tag--muted">preview: none found</span>
                        <?php endif; ?>
                        <form class="admin-links__category-form" method="POST" action="/admin/links/<?= (int) $link['id'] ?>/category">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                            <label class="visually-hidden" for="link_item_category_<?= (int) $link['id'] ?>">Category</label>
                            <select class="form__input form__input--sm" id="link_item_category_<?= (int) $link['id'] ?>" name="category_id">
                                <option value="">Uncategorized</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>"<?= (int) ($link['category_id'] ?? 0) === (int) $category['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn--ghost btn--sm">Set category</button>
                        </form>
                    </div>
                    <div class="admin-links__actions">
                        <form method="POST" action="/admin/links/<?= (int) $link['id'] ?>/refetch-preview">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                            <button type="submit" class="btn btn--ghost btn--sm" title="Re-fetch preview image">↺</button>
                        </form>
                        <form method="POST" action="/admin/links/<?= (int) $link['id'] ?>/delete">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                            <button type="submit" class="btn btn--ghost btn--sm" onclick="return confirm('Delete this link?')">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Link Categories</h2>
    <form method="POST" action="/admin/link-categories" class="form admin-categories__create">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">

        <label class="form__label" for="category_name">Category name <span aria-hidden="true">*</span></label>
        <input class="form__input" type="text" id="category_name" name="name" required maxlength="100" placeholder="e.g. Publishers">

        <label class="form__label" for="category_sort_order">Sort order</label>
        <input class="form__input" type="number" id="category_sort_order" name="sort_order" value="0" min="0" max="9999">

        <button class="btn btn--primary" type="submit">Create category</button>
    </form>

    <?php if (empty($categories)): ?>
        <p class="empty-state">No categories yet. Create one above.</p>
    <?php else: ?>
        <div class="admin-categories__list">
            <?php foreach ($categories as $category): ?>
                <div class="admin-categories__item">
                    <form method="POST" action="/admin/link-categories/<?= (int) $category['id'] ?>/rename" class="admin-categories__edit-form">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                        <input class="form__input form__input--sm" type="text" name="name" value="<?= htmlspecialchars((string) $category['name']) ?>" maxlength="100" required>
                        <input class="form__input form__input--sm" type="number" name="sort_order" value="<?= (int) $category['sort_order'] ?>" min="0" max="9999">
                        <button type="submit" class="btn btn--ghost btn--sm">Save</button>
                    </form>
                    <form method="POST" action="/admin/link-categories/<?= (int) $category['id'] ?>/delete" onsubmit="return confirm('Delete this category? Links in this category will become Uncategorized.');">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                        <button type="submit" class="btn btn--ghost btn--sm">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
