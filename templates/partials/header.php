<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Hut') ?> — Hut</title>
    <script>window.HUT_BASE_PATH = <?= json_encode(\Hut\Url::basePath(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<?php require __DIR__ . '/nav.php'; ?>
<main class="container">
<?php
// Flash messages
if (!empty($_SESSION['flash_error'])) {
    echo '<div class="flash flash--error">' . htmlspecialchars($_SESSION['flash_error']) . '</div>';
    unset($_SESSION['flash_error']);
}
if (!empty($_SESSION['flash_success'])) {
    echo '<div class="flash flash--success">' . htmlspecialchars($_SESSION['flash_success']) . '</div>';
    unset($_SESSION['flash_success']);
}
?>
