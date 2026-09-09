<?php $pageTitle = 'Ausgaben'; require __DIR__ . '/../partials/header.php'; ?>

<div class="page-header">
    <h1>Ausgaben</h1>
    <p class="page-header__sub">
        <a href="<?= htmlspecialchars($editUrl) ?>" target="_blank" rel="noopener noreferrer">Open full spreadsheet to edit ↗</a>
    </p>
</div>

<div class="card sheet-embed">
    <iframe src="<?= htmlspecialchars($embedUrl) ?>" class="sheet-embed__frame" loading="lazy" title="Ausgaben"></iframe>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
