<?php
/**
 * Administrator Overview & Command Dashboard
 * High-level system statistics, pending approvals queue, and AWS environment diagnostics.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = Database::getConnection();

// System Platform Statistics
$stats = [
    'total_events'   => (int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'pending_events' => (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'pending'")->fetchColumn(),
    'approved_events'=> (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved'")->fetchColumn(),
    'charities'      => (int)$pdo->query("SELECT COUNT(*) FROM charities")->fetchColumn(),
    'users'          => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'registrations'  => (int)$pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'confirmed'")->fetchColumn(),
];

// Fetch Pending Submissions
$stmtPending = $pdo->query("
    SELECT 
        e.*, 
        c.charity_name, 
        u.full_name AS organizer_name,
        u.email AS organizer_email,
        v.venue_name, 
        v.city AS venue_city, 
        v.is_online
    FROM events e
    INNER JOIN charities c ON e.charity_id = c.id
    INNER JOIN users u ON c.user_id = u.id
    INNER JOIN venues v ON e.venue_id = v.id
    WHERE e.status = 'pending'
    ORDER BY e.created_at ASC
");
$pendingEvents = $stmtPending->fetchAll();

// Handle quick approve/reject from dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $eventId = (int)($_POST['event_id'] ?? 0);

        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE events SET status = 'approved', rejection_reason = NULL, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $eventId]);
            set_flash('success', "Event #{$eventId} approved successfully and is now publicly visible.");
        } elseif ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? 'Event does not meet community guidelines.');
            $stmt = $pdo->prepare("UPDATE events SET status = 'rejected', rejection_reason = :reason, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $eventId, 'reason' => $reason]);
            set_flash('info', "Event #{$eventId} was rejected. Feedback provided to organizer.");
        }
    }
    header('Location: /admin/dashboard.php');
    exit;
}

$pageTitle = 'Administrator Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Admin Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link active" href="/admin/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Overview</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/admin/pending-events.php">
                <i class="bi bi-hourglass-split me-1"></i>Pending Approvals
                <?php if ($stats['pending_events'] > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $stats['pending_events'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/admin/manage-events.php"><i class="bi bi-calendar-event me-1"></i>All Events</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/admin/manage-charities.php"><i class="bi bi-building me-1"></i>Charities</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/admin/manage-users.php"><i class="bi bi-people me-1"></i>Users & Roles</a>
        </li>
    </ul>

    <!-- Header Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="fw-bold mb-1"><i class="bi bi-shield-check text-danger me-2"></i>Admin Command Center</h1>
            <p class="text-muted small mb-0">Platform oversight, event review pipeline, and cloud deployment status</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/health.php" target="_blank" class="btn btn-outline-success btn-sm">
                <i class="bi bi-heart-pulse me-1"></i> Health Status (ALB Check)
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card p-3">
                <div class="stat-icon bg-warning-subtle text-warning" style="width: 44px; height: 44px; font-size: 1.25rem;">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0 text-danger"><?= $stats['pending_events'] ?></h4>
                    <span class="text-muted small">Pending Review</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card p-3">
                <div class="stat-icon bg-success-subtle text-success" style="width: 44px; height: 44px; font-size: 1.25rem;">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $stats['approved_events'] ?></h4>
                    <span class="text-muted small">Live Events</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card p-3">
                <div class="stat-icon bg-primary-subtle text-primary" style="width: 44px; height: 44px; font-size: 1.25rem;">
                    <i class="bi bi-calendar3"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $stats['total_events'] ?></h4>
                    <span class="text-muted small">Total Events</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card p-3">
                <div class="stat-icon bg-info-subtle text-info" style="width: 44px; height: 44px; font-size: 1.25rem;">
                    <i class="bi bi-building"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $stats['charities'] ?></h4>
                    <span class="text-muted small">Charities</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card p-3">
                <div class="stat-icon bg-secondary-subtle text-secondary" style="width: 44px; height: 44px; font-size: 1.25rem;">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $stats['users'] ?></h4>
                    <span class="text-muted small">Users</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="stat-card p-3">
                <div class="stat-icon bg-danger-subtle text-danger" style="width: 44px; height: 44px; font-size: 1.25rem;">
                    <i class="bi bi-ticket-perforated"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $stats['registrations'] ?></h4>
                    <span class="text-muted small">RSVPs</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Events Review Queue -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-inbox-fill text-warning me-2"></i>Pending Event Submissions Awaiting Approval
                </h5>
                <p class="text-muted small mb-0">Workflow 3: Review submitted campaigns before they go live on the public calendar</p>
            </div>
            <?php if (!empty($pendingEvents)): ?>
                <span class="badge bg-danger rounded-pill"><?= count($pendingEvents) ?> pending</span>
            <?php endif; ?>
        </div>

        <?php if (empty($pendingEvents)): ?>
            <div class="text-center py-5 bg-light rounded-3">
                <i class="bi bi-check-circle text-success" style="font-size: 2.5rem;"></i>
                <h6 class="fw-bold mt-2 mb-1">Queue is clear!</h6>
                <p class="text-muted small mb-0">All submitted charity events have been reviewed and decided.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Event Title & Organizer</th>
                            <th>Category</th>
                            <th>Dates</th>
                            <th>Capacity</th>
                            <th class="text-end">Review Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingEvents as $p): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($p['title']) ?></div>
                                    <small class="text-muted">
                                        Org: <span class="fw-medium text-dark"><?= e($p['charity_name']) ?></span> (<?= e($p['organizer_name']) ?>)
                                    </small><br>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= $p['is_online'] ? 'Virtual Event' : e($p['venue_city']) ?></small>
                                </td>
                                <td><?= render_category_badge($p['category']) ?></td>
                                <td>
                                    <small class="fw-semibold"><?= format_datetime($p['start_datetime'], 'M j, Y') ?></small><br>
                                    <small class="text-muted"><?= format_time($p['start_datetime']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= (int)$p['capacity'] ?> spots</span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a href="/event-details.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Preview Event">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <!-- Approve Action Form -->
                                        <form method="POST" action="/admin/dashboard.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="event_id" value="<?= (int)$p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success fw-medium">
                                                <i class="bi bi-check-lg me-1"></i>Approve
                                            </button>
                                        </form>

                                        <!-- Reject Trigger Button with Modal -->
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= (int)$p['id'] ?>">
                                            <i class="bi bi-x-lg me-1"></i>Reject
                                        </button>
                                    </div>

                                    <!-- Rejection Reason Modal -->
                                    <div class="modal fade" id="rejectModal<?= (int)$p['id'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= (int)$p['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content text-start">
                                                <form method="POST" action="/admin/dashboard.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="event_id" value="<?= (int)$p['id'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold" id="modalLabel<?= (int)$p['id'] ?>">Reject Event Submission</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="small text-muted mb-3">Provide feedback to <strong><?= e($p['organizer_name']) ?></strong> explaining why this event was rejected. The organizer will see this feedback in their portal.</p>
                                                        <div class="mb-3">
                                                            <label for="reason<?= (int)$p['id'] ?>" class="form-label fw-semibold small">Rejection Reason <span class="text-danger">*</span></label>
                                                            <textarea class="form-control" id="reason<?= (int)$p['id'] ?>" name="rejection_reason" rows="3" placeholder="e.g. Please provide required municipal permits, insurance documentation, or clarify safety measures..." required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-sm btn-danger fw-semibold">Confirm Rejection</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cloud Diagnostics Box -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-hdd-network text-primary"></i> Cloud Infrastructure Diagnostics
        </h6>
        <div class="row g-3 small text-muted">
            <div class="col-md-3">
                <span class="d-block text-dark fw-semibold">Web Server & PHP</span>
                <span>PHP <?= PHP_VERSION ?> on <?= PHP_OS ?></span>
            </div>
            <div class="col-md-3">
                <span class="d-block text-dark fw-semibold">Database Engine</span>
                <span>MySQL via PDO (utf8mb4)</span>
            </div>
            <div class="col-md-3">
                <span class="d-block text-dark fw-semibold">Host / Hostname</span>
                <span><?= e(gethostname() ?: 'ec2-instance') ?></span>
            </div>
            <div class="col-md-3">
                <span class="d-block text-dark fw-semibold">AWS Secrets Manager</span>
                <span><?= filter_var(getenv('USE_AWS_SECRETS') ?: 'false', FILTER_VALIDATE_BOOLEAN) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Direct (.env)</span>' ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
