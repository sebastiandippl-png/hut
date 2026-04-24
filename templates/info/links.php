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
                $initial = strtoupper(substr($domain !== '' ? $domain : (string) $link['title'], 0, 1));
                // Pick a hue from a curated palette based on the domain so each card feels distinct.
                $hues = [264, 195, 330, 155, 28, 218, 300, 60];
                $hue  = $hues[abs(crc32($domain)) % count($hues)];
            ?>
            <a class="link-card" href="<?= htmlspecialchars((string) $link['url']) ?>" target="_blank" rel="noopener noreferrer" style="--card-hue:<?= $hue ?>">
                <?php if (!empty($link['preview_image_url'])): ?>
                    <div class="link-card__preview">
                        <img src="<?= htmlspecialchars((string) $link['preview_image_url']) ?>" alt="" loading="lazy">
                    </div>
                <?php endif; ?>
                <div class="link-card__body">
                    <div class="link-card__header">
                        <span class="link-card__icon" aria-hidden="true"><?= htmlspecialchars($initial) ?></span>
                        <span class="link-card__title"><?= htmlspecialchars((string) $link['title']) ?></span>
                        <span class="link-card__arrow" aria-hidden="true">↗</span>
                    </div>
                    <?php if (!empty($link['description'])): ?>
                        <span class="link-card__desc"><?= htmlspecialchars((string) $link['description']) ?></span>
                    <?php endif; ?>
                    <?php if ($domain !== ''): ?>
                        <span class="link-card__domain"><?= htmlspecialchars($domain) ?></span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
