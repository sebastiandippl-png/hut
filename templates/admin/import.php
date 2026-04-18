<?php
$pageTitle = 'Admin';
$currentUser = \Hut\Auth::user();
require __DIR__ . '/../partials/header.php';
?>

<div class="page-header">
    <h1>Admin</h1>
    <p class="page-header__sub">Manage imports and user accounts</p>
</div>

<section class="card admin-users">
    <h2>Fetch BGG User Collections</h2>
    <p class="page-header__sub">Configured users from .env: <?= !empty($configuredCollectionUsers) ? htmlspecialchars(implode(', ', $configuredCollectionUsers)) : 'none' ?></p>
    <p class="page-header__sub">Rows currently stored in user_collection: <?= (int) $collectionRowCount ?></p>
    <form method="POST" action="/admin/collections/fetch" onsubmit="return confirm('Fetch and replace collection rows for configured BGG users?');">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
        <button class="btn btn--primary" type="submit">Fetch configured collections</button>
    </form>
</section>

<div class="card">
    <h2>Import BGG Collection</h2>
    <p class="page-header__sub">Upload the BGG rankings CSV export used by this project.</p>
    <ol>
        <li>Upload a CSV shaped like <strong>boardgames_ranks.csv</strong></li>
        <li>Required columns are <strong>id</strong>, <strong>name</strong>, and <strong>yearpublished</strong></li>
        <li>Optional ranking columns like <strong>rank</strong>, <strong>average</strong>, and <strong>bayesaverage</strong> are stored directly in the games table</li>
        <li>XML uploads are still accepted, but they can only populate the subset of fields that overlap with the CSV-based schema</li>
    </ol>
</div>

<form
    class="form card"
    method="POST"
    action="/admin/import"
    enctype="multipart/form-data"
    data-import-form
    data-import-start-url="<?= htmlspecialchars(\Hut\Url::to('/admin/import/start')) ?>"
    data-import-process-url="<?= htmlspecialchars(\Hut\Url::to('/admin/import/process')) ?>"
    data-import-status-url="<?= htmlspecialchars(\Hut\Url::to('/admin/import/status')) ?>"
>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">

    <label class="form__label" for="bgg_file">BGG export file</label>
    <input class="form__input" type="file" id="bgg_file" name="bgg_file" accept=".csv,text/csv,text/plain,application/vnd.ms-excel,.xml,text/xml,application/xml" required>

    <button class="btn btn--primary" type="submit" data-import-submit>Import</button>

    <div class="import-progress" data-import-progress hidden>
        <div class="import-progress__header">
            <strong data-import-status-label>Preparing import...</strong>
            <span data-import-percent>0%</span>
        </div>
        <progress class="import-progress__bar" value="0" max="100" data-import-progress-bar></progress>
        <div class="import-progress__stats" data-import-stats>Processed 0 of 0 rows</div>
        <div class="import-progress__message" data-import-message></div>
    </div>
</form>

<section class="card admin-users">
    <h2>Users</h2>
    <p class="page-header__sub">Delete user accounts from the application. Related collection and vote data is deleted automatically.</p>

    <?php if (empty($users)): ?>
        <p>No users found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Collection</th>
                        <th>Votes</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $managedUser): ?>
                        <?php
                        $isCurrentUser = $currentUser && (int) $currentUser['id'] === (int) $managedUser['id'];
                        $safeName = htmlspecialchars((string) $managedUser['name']);
                        $safeEmail = htmlspecialchars((string) $managedUser['email']);
                        ?>
                        <tr>
                            <td><?= $safeName ?></td>
                            <td><?= $safeEmail ?></td>
                            <td><?= (int) $managedUser['is_admin'] === 1 ? 'Admin' : 'User' ?></td>
                            <td><?= (int) $managedUser['is_approved'] === 1 ? 'Approved' : 'Pending' ?></td>
                            <td><?= (int) $managedUser['selected_games'] ?></td>
                            <td><?= (int) $managedUser['votes_count'] ?></td>
                            <td><?= htmlspecialchars((string) $managedUser['created_at']) ?></td>
                            <td>
                                <?php if ($isCurrentUser): ?>
                                    <span class="admin-table__self">Current user</span>
                                <?php else: ?>
                                    <div class="admin-table__actions">
                                        <?php if ((int) $managedUser['is_approved'] === 0): ?>
                                            <form method="POST" action="/admin/users/<?= (int) $managedUser['id'] ?>/approve" style="display: inline;">
                                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                                                <button class="btn btn--success btn--small" type="submit">Approve</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="/admin/users/<?= (int) $managedUser['id'] ?>/disapprove" style="display: inline;">
                                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                                                <button class="btn btn--warning btn--small" type="submit">Disapprove</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="/admin/users/<?= (int) $managedUser['id'] ?>/delete" onsubmit="return confirm('Delete this user account? This cannot be undone.');" style="display: inline;">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Hut\Auth::csrfToken()) ?>">
                                            <button class="btn btn--danger btn--small" type="submit">Delete</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
