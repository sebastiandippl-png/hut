<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Hut</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="container">
<div class="error-page">
    <h1>500</h1>
    <p>Something went wrong on our end. Please try again in a moment.</p>
    <?php if (!empty($errorRefId)): ?>
        <p style="font-size:.85em;color:#888;">Reference: <code><?= htmlspecialchars($errorRefId) ?></code></p>
    <?php endif; ?>
    <?php if (!empty($debugInfo)): ?>
        <pre style="text-align:left;background:#f5f5f5;padding:1em;overflow:auto;font-size:.8em;"><?= htmlspecialchars($debugInfo) ?></pre>
    <?php endif; ?>
    <a class="btn btn--primary" href="/games">Go to games</a>
</div>
</main>
</body>
</html>
