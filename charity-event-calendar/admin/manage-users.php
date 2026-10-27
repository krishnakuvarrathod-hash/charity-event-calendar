<?php
/**
 * Administrator Users & Roles Management
 * Audit user accounts and adjust system privileges safely.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = Database::getConnection();
$currentAdminId = current_user_id();

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';

// Handle Role Change / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);

        if ($targetUserId === $currentAdminId && in_array($action, ['change_role', 'delete'], true)) {
            set_flash('danger', 'Safety Protection: You cannot alter privileges or delete your own active administrator account.');
        } else {
            if ($action === 'change_role') {
                $newRole = trim($_POST['new_role'] ?? '');
                if (in_array($newRole, ['visitor', 'organizer', 'admin'], true)) {
                    $stmt = $pdo->prepare('UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id');
                    $stmt->execute(['role' => $newRole, 'id' => $targetUserId]);
                    set_flash('success', "User #{$targetUserId} role updated to " . ucfirst($newRole) . ".");
                }
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute(['id' => $targetUserId]);
                set_flash('success', "User account #{$targetUserId} was deleted.");
            }
        }
    }
    header('Location: /admin/manage-users.php');
    exit;
}

// Build query
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(full_name LIKE :search OR email LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($roleFilter) && in_array($roleFilter, ['visitor', 'organizer', 'admin'], true)) {
    $conditions[] = "role = :role";
    $params['role'] = $roleFilter;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $pdo->prepare("SELECT * FROM users {$whereClause} ORDER BY id ASC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users & Roles';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Admin Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link" href="/admin/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Overview</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/admin/pending-events.php"><i class="bi bi-hourglass-split me-1"></i>Pending Approvals</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/admin/manage-events.php"><i class="bi bi-calendar-event me-1"></i>All Events</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/admin/manage-charities.php"><i class="bi bi-building me-1"></i>Charities</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="/admin/manage-users.php"><i class="bi bi-people me-1"></i>Users & Roles</a>
        </li>
    </ul>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">User & Role Management</h1>
            <p class="text-muted small mb-0">Manage account privileges, organizers, visitors, and administrators</p>
        </div>
        <span class="badge bg-secondary fs-6 px-3 py-2"><?= count($users) ?> Total Users</span>
    </div>

    <!-- Filter Card -->
    <div class="filter-card mb-4">
        <form method="GET" action="/admin/manage-users.php" class="row g-3">
            <div class="col-md-7">
                <label for="search" class="form-label small fw-semibold text-muted">Search Name or Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Filter by name or email...">
                </div>
            </div>
            <div class="col-md-3">
                <label for="role" class="form-label small fw-semibold text-muted">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All Roles</option>
                    <option value="visitor" <?= ($roleFilter === 'visitor') ? 'selected' : '' ?>>Visitor</option>
                    <option value="organizer" <?= ($roleFilter === 'organizer') ? 'selected' : '' ?>>Organizer</option>
                    <option value="admin" <?= ($roleFilter === 'admin') ? 'selected' : '' ?>>Administrator</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>ID</th>
                        <th>User Name</th>
                        <th>Email Address</th>
                        <th>Current Role</th>
                        <th>Member Since</th>
                        <th class="text-end">Assign Role / Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="text-muted small fw-bold">#<?= (int)$u['id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                                <?php if ((int)$u['id'] === $currentAdminId): ?>
                                    <span class="badge bg-primary-subtle text-primary border">You</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($u['email']) ?></code></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-shield-lock-fill me-1"></i>Admin</span>
                                <?php elseif ($u['role'] === 'organizer'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-building me-1"></i>Organizer</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border"><i class="bi bi-person me-1"></i>Visitor</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= format_date($u['created_at']) ?></small></td>
                            <td class="text-end">
                                <?php if ((int)$u['id'] !== $currentAdminId): ?>
                                    <div class="d-inline-flex gap-2 align-items-center">
                                        <!-- Role selection form -->
                                        <form method="POST" action="/admin/manage-users.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>">
                                            <select name="new_role" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                <option value="visitor" <?= ($u['role'] === 'visitor') ? 'selected' : '' ?>>Visitor</option>
                                                <option value="organizer" <?= ($u['role'] === 'organizer') ? 'selected' : '' ?>>Organizer</option>
                                                <option value="admin" <?= ($u['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                                            </select>
                                        </form>

                                        <form method="POST" action="/admin/manage-users.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete user '<?= e($u['full_name']) ?>'?">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Active Account</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
