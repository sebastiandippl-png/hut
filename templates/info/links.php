<?php $pageTitle = 'Links'; require __DIR__ . '/../partials/header.php'; ?>

<div class="page-header">
    <h1>Links</h1>
</div>

<?php if (empty($links)): ?>
    <div class="card">
        <p class="empty-state">No links have been added yet. Check back soon!</p>
    </div>
<?php else: ?>
    <div class="links-grid">
        <?php foreach ($links as $link): ?>
            <?php
                $domain = '';
                $parsed = parse_url((string) $link['url']);
                if (!empty($parsed['host'])) {
                    $domain = preg_replace('/^www\./', '', (string) $parsed['host']);
                }
            ?>
            <a class="link-card" href="<?= htmlspecialchars((string) $link['url']) ?>" target="_blank" rel="noopener noreferrer">
                <div class="link-card__body">
                    <span class="link-card__title"><?= htmlspecialchars((string) $link['title']) ?></span>
                    <?php if (!empty($link['description'])): ?>
                        <span class="link-card__desc"><?= htmlspecialchars((string) $link['description']) ?></span>
                    <?php endif; ?>
                    <?php if ($domain !== ''): ?>
                        <span class="link-card__domain tag tag--muted"><?= htmlspecialchars($domain) ?></span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
