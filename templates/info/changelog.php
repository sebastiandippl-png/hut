<?php $pageTitle = 'Changelog'; require __DIR__ . '/../partials/header.php'; ?>

<div class="page-header">
    <h1>Changelog</h1>
    <p class="page-header__sub">Recent updates deployed from this repository.</p>
</div>

<section class="changelog" aria-label="Recent commits">
    <div class="changelog__meta">
        <?php if (!empty($generatedAt)): ?>
            <p>Generated at <?= htmlspecialchars($generatedAt) ?></p>
        <?php endif; ?>
        <?php if (!empty($repoUrl)): ?>
            <p>Repository: <a href="<?= htmlspecialchars($repoUrl) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($repoUrl) ?></a></p>
        <?php endif; ?>
    </div>

    <?php if (empty($entries)): ?>
        <div class="card">
            <p class="empty-state">No changelog entries found yet. Run <code>./ionos_deploy.sh</code> to generate the latest changelog data.</p>
        </div>
    <?php else: ?>
        <ol class="changelog__list">
            <?php foreach ($entries as $entry): ?>
                <li class="changelog__entry">
                    <article class="changelog__card">
                        <header class="changelog__entry-header">
                            <?php if (!empty($entry['url'])): ?>
                                <a class="changelog__hash" href="<?= htmlspecialchars($entry['url']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= htmlspecialchars($entry['short_hash'] !== '' ? $entry['short_hash'] : $entry['hash']) ?>
                                </a>
                            <?php else: ?>
                                <span class="changelog__hash changelog__hash--muted"><?= htmlspecialchars($entry['short_hash'] !== '' ? $entry['short_hash'] : $entry['hash']) ?></span>
                            <?php endif; ?>
                            <span class="changelog__date"><?= htmlspecialchars($entry['date']) ?></span>
                            <span class="changelog__author"><?= htmlspecialchars($entry['author']) ?></span>
                        </header>
                        <?php if (!empty($entry['url'])): ?>
                            <p class="changelog__subject"><a href="<?= htmlspecialchars($entry['url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($entry['subject']) ?></a></p>
                        <?php else: ?>
                            <p class="changelog__subject"><?= htmlspecialchars($entry['subject']) ?></p>
                        <?php endif; ?>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
