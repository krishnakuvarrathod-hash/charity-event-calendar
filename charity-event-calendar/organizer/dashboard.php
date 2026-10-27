<?php
/**
 * Charity Organizer Hub & Dashboard
 * Overview of organizer metrics, quick event actions, and recent submissions.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('organizer');

$pdo = Database::getConnection();
$userId = current_user_id();

// Fetch organizer's charity profile
$charity = get_organizer_charity($pdo, $userId);

$stats = [
    'total_events' => 0,
    'approved_events' => 0,
    'pending_events' => 0,
    'total_registrations' => 0,
];

$recentEvents = [];

if ($charity) {
    $charityId = (int)$charity['id'];

    // Stats
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM events WHERE charity_id = :cid");
    $stmtTotal->execute(['cid' => $charityId]);
    $stats['total_events'] = (int)$stmtTotal->fetchColumn();

    $stmtApproved = $pdo->prepare("SELECT COUNT(*) FROM events WHERE charity_id = :cid AND status = 'approved'");
    $stmtApproved->execute(['cid' => $charityId]);
    $stats['approved_events'] = (int)$stmtApproved->fetchColumn();

    $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM events WHERE charity_id = :cid AND status = 'pending'");
    $stmtPending->execute(['cid' => $charityId]);
    $stats['pending_events'] = (int)$stmtPending->fetchColumn();

    $stmtRegs = $pdo->prepare("
        SELECT COUNT(r.id) 
        FROM registrations r
        INNER JOIN events e ON r.event_id = e.id
        WHERE e.charity_id = :cid AND r.status = 'confirmed'
    ");
    $stmtRegs->execute(['cid' => $charityId]);
    $stats['total_registrations'] = (int)$stmtRegs->fetchColumn();

    // Recent events
    $stmtRecent = $pdo->prepare("
        SELECT 
            e.*, 
            v.venue_name, 
            v.city AS venue_city, 
            v.is_online,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'confirmed') AS confirmed_registrations
        FROM events e
        INNER JOIN venues v ON e.venue_id = v.id
        WHERE e.charity_id = :cid
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $stmtRecent->execute(['cid' => $charityId]);
    $recentEvents = $stmtRecent->fetchAll();
}

$pageTitle = 'Organizer Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Navigation pill tabs -->
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link active" href="/organizer/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/organizer/my-events.php"><i class="bi bi-calendar3 me-1"></i>My Events</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/organizer/profile.php"><i class="bi bi-building me-1"></i>Charity Profile</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/organizer/registrations.php"><i class="bi bi-people me-1"></i>Registrations</a>
        </li>
    </ul>

    <!-- Welcome & Action Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="fw-bold mb-1">Organizer Dashboard</h1>
            <p class="text-muted small mb-0">
                <?= $charity ? 'Managing causes for <strong class="text-dark">' . e($charity['charity_name']) . '</strong>' : 'Welcome to your organizer workspace' ?>
            </p>
        </div>
        <div>
            <?php if ($charity): ?>
                <a href="/organizer/create-event.php" class="btn btn-coral">
                    <i class="bi bi-plus-circle me-1"></i> Submit New Event
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Charity Profile Missing Notice -->
    <?php if (!$charity): ?>
        <div class="alert alert-warning border-warning shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                <div>
                    <h5 class="fw-bold mb-1">Set Up Your Charity Profile First</h5>
                    <p class="mb-3 small">Before you can submit charity events and fundraising drives, you need to provide your nonprofit organization details.</p>
                    <a href="/organizer/profile.php" class="btn btn-warning fw-semibold">
                        <i class="bi bi-building-add me-1"></i> Complete Charity Profile Now
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?= $stats['total_events'] ?></h3>
                    <span class="text-muted small">Total Events</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?= $stats['approved_events'] ?></h3>
                    <span class="text-muted small">Approved & Live</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?= $stats['pending_events'] ?></h3>
                    <span class="text-muted small">Pending Review</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-danger-subtle text-danger">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?= $stats['total_registrations'] ?></h3>
                    <span class="text-muted small">Confirmed RSVPs</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Events Management Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Recent Event Submissions</h5>
            <?php if ($stats['total_events'] > 5): ?>
                <a href="/organizer/my-events.php" class="btn btn-sm btn-outline-primary">View All (<?= $stats['total_events'] ?>)</a>
            <?php endif; ?>
        </div>

        <?php if (empty($recentEvents)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-plus text-muted" style="font-size: 2.5rem;"></i>
                <p class="text-muted mt-2 mb-3">No events submitted yet.</p>
                <?php if ($charity): ?>
                    <a href="/organizer/create-event.php" class="btn btn-sm btn-primary">Create Your First Event</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Event Details</th>
                            <th>Category</th>
                            <th>Event Date</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentEvents as $e): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($e['title']) ?></div>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= $e['is_online'] ? 'Virtual' : e($e['venue_city']) ?></small>
                                </td>
                                <td><?= render_category_badge($e['category']) ?></td>
                                <td>
                                    <small class="fw-medium"><?= format_datetime($e['start_datetime'], 'M j, Y') ?></small><br>
                                    <small class="text-muted"><?= format_time($e['start_datetime']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-people me-1"></i><?= (int)$e['confirmed_registrations'] ?> / <?= (int)$e['capacity'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?= render_status_badge($e['status']) ?>
                                    <?php if ($e['status'] === 'rejected' && !empty($e['rejection_reason'])): ?>
                                        <div class="text-danger small mt-1" style="max-width: 200px;">
                                            <i class="bi bi-info-circle me-1"></i><?= e($e['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/event-details.php?id=<?= (int)$e['id'] ?>" class="btn btn-outline-secondary" title="View Public Page">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="/organizer/edit-event.php?id=<?= (int)$e['id'] ?>" class="btn btn-outline-primary" title="Edit Event">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
