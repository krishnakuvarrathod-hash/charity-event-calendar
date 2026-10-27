<?php
/**
 * Administrator Charities Management
 * View, audit, and manage registered charity organizations.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = Database::getConnection();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validate_csrf()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $charityId = (int)($_POST['charity_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM charities WHERE id = :id');
        $stmt->execute(['id' => $charityId]);
        set_flash('success', "Charity #{$charityId} and its associated events have been removed.");
    }
    header('Location: /admin/manage-charities.php');
    exit;
}

$stmt = $pdo->query("
    SELECT 
        c.*, 
        u.full_name AS organizer_name, 
        u.email AS organizer_email,
        (SELECT COUNT(*) FROM events e WHERE e.charity_id = c.id) AS total_events,
        (SELECT COUNT(*) FROM events e WHERE e.charity_id = c.id AND e.status = 'approved') AS approved_events
    FROM charities c
    INNER JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
");
$charities = $stmt->fetchAll();

$pageTitle = 'Manage Charities';
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
            <a class="nav-link active" href="/admin/manage-charities.php"><i class="bi bi-building me-1"></i>Charities</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/admin/manage-users.php"><i class="bi bi-people me-1"></i>Users & Roles</a>
        </li>
    </ul>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Registered Charities</h1>
            <p class="text-muted small mb-0">Overview of partner nonprofit organizations and affiliated organizers</p>
        </div>
        <span class="badge bg-primary fs-6 px-3 py-2"><?= count($charities) ?> Charities</span>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>Charity Name</th>
                        <th>Organizer Contact</th>
                        <th>Location</th>
                        <th>Website</th>
                        <th>Events Hosted</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($charities as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">
                                    <a href="/charity-details.php?id=<?= (int)$c['id'] ?>" class="text-decoration-none text-dark">
                                        <?= e($c['charity_name']) ?>
                                    </a>
                                </div>
                                <small class="text-muted"><?= e(mb_strimwidth($c['description'], 0, 75, '...')) ?></small>
                            </td>
                            <td>
                                <div class="fw-medium text-dark"><?= e($c['organizer_name']) ?></div>
                                <small class="text-muted"><?= e($c['organizer_email']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-geo-alt me-1"></i><?= e($c['city']) ?>, <?= e($c['state']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($c['website'])): ?>
                                    <a href="<?= e($c['website']) ?>" target="_blank" rel="noopener" class="text-decoration-none small">
                                        <i class="bi bi-link-45deg me-1"></i>Visit <i class="bi bi-box-arrow-up-right" style="font-size: 10px;"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success me-1"><?= (int)$c['approved_events'] ?> live</span>
                                <span class="text-muted small">(<?= (int)$c['total_events'] ?> total)</span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="/charity-details.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Public Profile">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <form method="POST" action="/admin/manage-charities.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="charity_id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to remove this charity? All associated events and registrations will also be deleted.">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
