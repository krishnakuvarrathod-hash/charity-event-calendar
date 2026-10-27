<?php
/**
 * Administrator Event Management
 * Browse, filter, edit status, or delete any event on the platform.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = Database::getConnection();

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Handle Actions (Delete / Status Change)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $eventId = (int)($_POST['event_id'] ?? 0);

        if ($action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
            $stmt->execute(['id' => $eventId]);
            set_flash('success', "Event #{$eventId} has been permanently deleted.");
        } elseif ($action === 'update_status') {
            $newStatus = $_POST['new_status'] ?? '';
            if (in_array($newStatus, ['approved', 'pending', 'rejected', 'cancelled'], true)) {
                $reason = ($newStatus === 'rejected') ? trim($_POST['rejection_reason'] ?? 'Rejected by administrative action.') : null;
                $stmt = $pdo->prepare('UPDATE events SET status = :status, rejection_reason = :reason, updated_at = NOW() WHERE id = :id');
                $stmt->execute(['status' => $newStatus, 'reason' => $reason, 'id' => $eventId]);
                set_flash('success', "Event #{$eventId} status updated to " . ucfirst($newStatus) . ".");
            }
        }
    }
    header('Location: /admin/manage-events.php' . (!empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''));
    exit;
}

// Build Query
$conditions = [];
$params = [];

if (!empty($statusFilter) && in_array($statusFilter, ['approved', 'pending', 'rejected', 'cancelled'], true)) {
    $conditions[] = "e.status = :status";
    $params['status'] = $statusFilter;
}

if (!empty($search)) {
    $conditions[] = "(e.title LIKE :search OR c.charity_name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "
    SELECT 
        e.*, 
        c.charity_name, 
        v.venue_name, 
        v.city AS venue_city, 
        v.is_online,
        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'confirmed') AS confirmed_registrations
    FROM events e
    INNER JOIN charities c ON e.charity_id = c.id
    INNER JOIN venues v ON e.venue_id = v.id
    {$whereClause}
    ORDER BY e.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$pageTitle = 'Manage All Events';
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
            <a class="nav-link active" href="/admin/manage-events.php"><i class="bi bi-calendar-event me-1"></i>All Events</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/admin/manage-charities.php"><i class="bi bi-building me-1"></i>Charities</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/admin/manage-users.php"><i class="bi bi-people me-1"></i>Users & Roles</a>
        </li>
    </ul>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="fw-bold mb-1">All Event Listings</h1>
            <p class="text-muted small mb-0">Inspect, moderate, or remove events across all nonprofit organizers</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/pending-events.php" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-hourglass-split me-1"></i>Review Pending Queue
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card mb-4">
        <form method="GET" action="/admin/manage-events.php" class="row g-3">
            <div class="col-md-7">
                <label for="search" class="form-label small fw-semibold text-muted">Search Title or Charity</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Filter events...">
                </div>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="approved" <?= ($statusFilter === 'approved') ? 'selected' : '' ?>>Approved</option>
                    <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="rejected" <?= ($statusFilter === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                    <option value="cancelled" <?= ($statusFilter === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Events Table -->
    <?php if (empty($events)): ?>
        <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-4">
            <h5 class="fw-bold">No Events Match Query</h5>
            <p class="text-muted small mb-0">Try clearing filters or search keywords.</p>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>ID</th>
                            <th>Title & Charity</th>
                            <th>Category</th>
                            <th>Event Date</th>
                            <th>RSVP / Cap</th>
                            <th>Status</th>
                            <th class="text-end">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td class="text-muted small fw-bold">#<?= (int)$ev['id'] ?></td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <a href="/event-details.php?id=<?= (int)$ev['id'] ?>" class="text-decoration-none text-dark">
                                            <?= e($ev['title']) ?>
                                        </a>
                                    </div>
                                    <small class="text-muted">By <?= e($ev['charity_name']) ?></small>
                                </td>
                                <td><?= render_category_badge($ev['category']) ?></td>
                                <td>
                                    <small class="fw-semibold"><?= format_datetime($ev['start_datetime'], 'M j, Y') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= (int)$ev['confirmed_registrations'] ?> / <?= (int)$ev['capacity'] ?>
                                    </span>
                                </td>
                                <td><?= render_status_badge($ev['status']) ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="/event-details.php?id=<?= (int)$ev['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <!-- Quick Status Dropdown Form -->
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Status
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                                                <li>
                                                    <form method="POST" action="/admin/manage-events.php">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                                                        <input type="hidden" name="new_status" value="approved">
                                                        <button type="submit" class="dropdown-item text-success"><i class="bi bi-check-circle me-1"></i>Set Approved</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="/admin/manage-events.php">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                                                        <input type="hidden" name="new_status" value="pending">
                                                        <button type="submit" class="dropdown-item text-warning"><i class="bi bi-hourglass-split me-1"></i>Set Pending</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="/admin/manage-events.php">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                                                        <input type="hidden" name="new_status" value="cancelled">
                                                        <button type="submit" class="dropdown-item text-secondary"><i class="bi bi-slash-circle me-1"></i>Set Cancelled</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>

                                        <!-- Permanent Delete Form -->
                                        <form method="POST" action="/admin/manage-events.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to permanently delete event #<?= (int)$ev['id'] ?>? This action cannot be undone.">
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
