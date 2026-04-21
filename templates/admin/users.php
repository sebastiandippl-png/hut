<?php
$pageTitle = 'Admin Users';
$currentUser = \Hut\Auth::user();
require __DIR__ . '/../partials/header.php';
?>

<div class="page-header">
    <h1>Users</h1>
    <p class="page-header__sub">Manage user access, approvals, and account lifecycle</p>
</div>

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
