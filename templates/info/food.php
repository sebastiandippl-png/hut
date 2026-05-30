<?php
$pageTitle = 'Food';
require __DIR__ . '/../partials/header.php';

$foodSuggestionCount = isset($suggestions) && is_array($suggestions) ? count($suggestions) : 0;

function linkifyFoodNames(string $names, array $map): string
{
    if ($names === '' || $names === 'No hearts yet') {
        return htmlspecialchars($names);
    }

    $parts = explode(', ', $names);
    $linked = array_map(static function (string $name) use ($map): string {
        $name = trim($name);
        if (isset($map[$name])) {
            return '<a href="/residents/' . $map[$name] . '">' . htmlspecialchars($name) . '</a>';
        }
        return htmlspecialchars($name);
    }, $parts);

    return implode(', ', $linked);
}
?>

<div class="page-header">
    <h1>Food</h1>
    <p class="page-header__sub"><?= (int) $foodSuggestionCount ?> dishes suggested so far.</p>
    <p class="page-header__sub">Suggest dishes for the hut week, heart the ones you want most, and keep the top picks at the top.</p>
</div>

<section class="food-page">
    <form class="food-form card" method="POST" action="/news/food">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
        <div class="food-form__fields">
            <label class="food-form__field">
                <span class="food-form__label">Food suggestion</span>
                <input class="food-form__input" type="text" name="title" maxlength="255" placeholder="e.g. pasta bake, chili, curry" required>
            </label>
            <label class="food-form__field food-form__field--notes">
                <span class="food-form__label">Notes</span>
                <textarea class="food-form__textarea" name="notes" rows="3" placeholder="Optional ingredients, servings, or prep notes"></textarea>
            </label>
        </div>
        <div class="food-form__actions">
            <button class="btn btn--ghost" type="submit">Add suggestion</button>
        </div>
    </form>

    <?php if (empty($suggestions)): ?>
        <div class="card">
            <p class="empty-state">No food suggestions yet. Add the first one above.</p>
        </div>
    <?php else: ?>
        <div class="food-list">
            <?php foreach ($suggestions as $suggestion): ?>
                <?php
                    $suggestionId = (int) $suggestion['id'];
                    $hearts = (int) $suggestion['hearts'];
                    $isOwner = (int) $suggestion['user_id'] === (int) \Hut\Auth::user()['id'];
                    $suggestionTitle = (string) $suggestion['title'];
                    $suggestionNotes = (string) ($suggestion['notes'] ?? '');
                    $suggestionImageUrl = trim((string) ($suggestion['image_url'] ?? ''));
                    $suggestionImageSourceUrl = trim((string) ($suggestion['image_source_url'] ?? ''));
                    $suggestionImageCreator = trim((string) ($suggestion['image_creator'] ?? ''));
                    $suggestionImageLicense = trim((string) ($suggestion['image_license'] ?? ''));
                    $createdAt = '';
                    if (!empty($suggestion['created_at'])) {
                        $timestamp = strtotime((string) $suggestion['created_at']);
                        if ($timestamp !== false) {
                            $createdAt = date('d.m.Y', $timestamp);
                        }
                    }
                    $suggestedBy = trim((string) ($suggestion['suggested_by_name'] ?? ''));
                ?>
                <article class="card food-card" data-heart-id="<?= $suggestionId ?>" data-hearts="<?= $hearts ?>">
                    <?php if ($suggestionImageUrl !== ''): ?>
                        <a class="food-card__image-link" href="<?= htmlspecialchars($suggestionImageSourceUrl !== '' ? $suggestionImageSourceUrl : $suggestionImageUrl) ?>" target="_blank" rel="noopener noreferrer">
                            <img class="food-card__image" src="<?= htmlspecialchars($suggestionImageUrl) ?>" alt="<?= htmlspecialchars($suggestionTitle) ?>" loading="lazy">
                        </a>
                        <?php if ($suggestionImageCreator !== '' || $suggestionImageLicense !== ''): ?>
                            <p class="food-card__image-credit">
                                Image by <?= htmlspecialchars($suggestionImageCreator !== '' ? $suggestionImageCreator : 'Openverse source') ?>
                                <?php if ($suggestionImageLicense !== ''): ?>
                                    · <?= htmlspecialchars($suggestionImageLicense) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="food-card__header">
                        <h2 class="food-card__title"><?= htmlspecialchars((string) $suggestion['title']) ?></h2>
                        <?php if ($isOwner): ?>
                            <div class="food-card__owner-actions">
                                <form method="POST" action="/news/food/<?= $suggestionId ?>/delete" class="food-card__delete-form">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                                    <button class="btn btn--ghost food-card__delete" type="submit">Delete</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty(trim((string) $suggestion['notes']))): ?>
                        <p class="food-card__notes"><?= htmlspecialchars((string) $suggestion['notes']) ?></p>
                    <?php endif; ?>

                    <?php if ($isOwner): ?>
                        <details class="food-edit-disclosure">
                            <summary class="btn btn--ghost food-edit-disclosure__summary">Edit suggestion</summary>
                            <form class="food-edit-form" method="POST" action="/news/food/<?= $suggestionId ?>/update">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                                <label class="food-form__field">
                                    <span class="food-form__label">Edit food suggestion</span>
                                    <input class="food-form__input" type="text" name="title" maxlength="255" value="<?= htmlspecialchars($suggestionTitle) ?>" required>
                                </label>
                                <label class="food-form__field food-form__field--notes">
                                    <span class="food-form__label">Notes</span>
                                    <textarea class="food-form__textarea" name="notes" rows="3"><?= htmlspecialchars($suggestionNotes) ?></textarea>
                                </label>
                                <div class="food-form__actions food-form__actions--inline">
                                    <button class="btn btn--ghost" type="submit">Save changes</button>
                                </div>
                            </form>
                        </details>
                    <?php endif; ?>

                    <div class="food-card__meta">
                        <span class="food-card__meta-item">Suggested by <?= htmlspecialchars($suggestedBy !== '' ? $suggestedBy : 'Unknown') ?></span>
                        <?php if ($createdAt !== ''): ?>
                            <span class="food-card__meta-item">Added <?= htmlspecialchars($createdAt) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="heart-controls" data-heart-id="<?= $suggestionId ?>" data-heart-endpoint="/news/food/<?= $suggestionId ?>/heart">
                        <button class="btn btn--heart <?= (int) $suggestion['hearted_by_me'] === 1 ? 'btn--heart--active' : '' ?>"
                                data-heart-button
                                data-heart-id="<?= $suggestionId ?>">
                            <?= (int) $suggestion['hearted_by_me'] === 1 ? '♥ Hearted' : '♥ Heart' ?>
                        </button>
                        <span class="heart-summary"><span class="heart-tally"><?= $hearts ?></span> <span class="heart-label">heart<?= $hearts !== 1 ? 's' : '' ?></span></span>
                    </div>

                    <p class="food-card__hearted-by" data-hearted-by="<?= $suggestionId ?>" data-hearted-mode="names">Hearted by: <?= linkifyFoodNames((string) $suggestion['hearted_by'], $residentNameMap) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>window.HUT_RESIDENT_MAP = <?= json_encode($residentNameMap, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>