<?php
/**
 * Administrator Pending Event Review & Approval Screen
 * Workflow 3: Review event submissions, approve or reject with custom feedback.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = Database::getConnection();

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $action = $_POST['action'] ?? '';
        $eventId = (int)($_POST['event_id'] ?? 0);

        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE events SET status = 'approved', rejection_reason = NULL, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $eventId]);
            set_flash('success', "Event #{$eventId} was approved! It is now live on the public calendar.");
        } elseif ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? 'Event does not meet platform guidelines.');
            $stmt = $pdo->prepare("UPDATE events SET status = 'rejected', rejection_reason = :reason, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $eventId, 'reason' => $reason]);
            set_flash('info', "Event #{$eventId} was rejected with feedback provided to the organizer.");
        }
    }
    header('Location: /admin/pending-events.php');
    exit;
}

// Fetch All Pending Events
$stmt = $pdo->query("
    SELECT 
        e.*, 
        c.charity_name, 
        c.city AS charity_city,
        c.email AS charity_email,
        u.full_name AS organizer_name,
        u.email AS organizer_email,
        v.venue_name, 
        v.address AS venue_address,
        v.city AS venue_city, 
        v.state AS venue_state,
        v.is_online
    FROM events e
    INNER JOIN charities c ON e.charity_id = c.id
    INNER JOIN users u ON c.user_id = u.id
    INNER JOIN venues v ON e.venue_id = v.id
    WHERE e.status = 'pending'
    ORDER BY e.created_at ASC
");
$pendingEvents = $stmt->fetchAll();

$pageTitle = 'Pending Event Approvals';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Admin Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link" href="/admin/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Overview</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="/admin/pending-events.php">
                <i class="bi bi-hourglass-split me-1"></i>Pending Approvals
                <?php if (count($pendingEvents) > 0): ?>
                    <span class="badge bg-danger ms-1"><?= count($pendingEvents) ?></span>
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

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Pending Event Submissions</h1>
            <p class="text-muted small mb-0">Carefully review each organizer request before granting public publication</p>
        </div>
        <span class="badge bg-warning text-dark fs-6 px-3 py-2">
            <?= count($pendingEvents) ?> Submission<?= count($pendingEvents) === 1 ? '' : 's' ?> Pending
        </span>
    </div>

    <?php if (empty($pendingEvents)): ?>
        <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-4">
            <div class="avatar-circle bg-success-subtle text-success mx-auto mb-3" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                <i class="bi bi-patch-check-fill"></i>
            </div>
            <h4 class="fw-bold">No Pending Submissions</h4>
            <p class="text-muted small mb-0">All submitted events have been reviewed. New organizer submissions will show up here automatically.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($pendingEvents as $e): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                        <div class="row g-4 align-items-center">
                            <!-- Left: Event Image & Category -->
                            <div class="col-md-3">
                                <div class="rounded-3 overflow-hidden shadow-sm" style="height: 180px;">
                                    <img src="<?= get_event_image($e['image_url'], $e['category']) ?>" 
                                         alt="<?= e($e['title']) ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            </div>

                            <!-- Center: Event & Charity Info -->
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <?= render_category_badge($e['category']) ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending Review</span>
                                    <span class="small text-muted ms-auto">Submitted: <?= format_datetime($e['created_at']) ?></span>
                                </div>

                                <h4 class="fw-bold mb-2"><?= e($e['title']) ?></h4>
                                <p class="text-secondary small mb-3"><?= e(mb_strimwidth($e['description'], 0, 180, '...')) ?></p>

                                <div class="row g-2 small text-muted">
                                    <div class="col-sm-6">
                                        <i class="bi bi-building text-primary me-1"></i>
                                        <strong>Charity:</strong> <?= e($e['charity_name']) ?>
                                    </div>
                                    <div class="col-sm-6">
                                        <i class="bi bi-person text-primary me-1"></i>
                                        <strong>Organizer:</strong> <?= e($e['organizer_name']) ?> (<?= e($e['organizer_email']) ?>)
                                    </div>
                                    <div class="col-sm-6">
                                        <i class="bi bi-calendar-event text-primary me-1"></i>
                                        <strong>Timing:</strong> <?= format_datetime($e['start_datetime'], 'M j, Y • g:i A') ?>
                                    </div>
                                    <div class="col-sm-6">
                                        <i class="bi bi-geo-alt text-primary me-1"></i>
                                        <strong>Venue:</strong> <?= $e['is_online'] ? 'Virtual Event' : e($e['venue_name'] . ', ' . $e['venue_city']) ?>
                                    </div>
                                    <div class="col-sm-6">
                                        <i class="bi bi-people text-primary me-1"></i>
                                        <strong>Capacity:</strong> <?= (int)$e['capacity'] ?> attendees max
                                    </div>
                                    <div class="col-sm-6">
                                        <i class="bi bi-clock-history text-primary me-1"></i>
                                        <strong>RSVP Deadline:</strong> <?= format_datetime($e['registration_deadline']) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Decision Buttons -->
                            <div class="col-md-3 text-md-end border-start-md ps-md-4">
                                <div class="d-grid gap-2">
                                    <a href="/event-details.php?id=<?= (int)$e['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Inspect Full Page
                                    </a>

                                    <form method="POST" action="/admin/pending-events.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
                                        <button type="submit" class="btn btn-success w-100 fw-semibold">
                                            <i class="bi bi-check-circle-fill me-1"></i> Approve Event
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal<?= (int)$e['id'] ?>">
                                        <i class="bi bi-x-circle me-1"></i> Reject with Reason
                                    </button>
                                </div>

                                <!-- Rejection Reason Modal -->
                                <div class="modal fade" id="rejectModal<?= (int)$e['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content text-start">
                                            <form method="POST" action="/admin/pending-events.php">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Reject Event Submission</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small text-muted mb-2">Provide a clear explanation for organizer <strong><?= e($e['organizer_name']) ?></strong>:</p>
                                                    <textarea class="form-control" name="rejection_reason" rows="4" placeholder="Detail required changes, permits, or reasons for rejection..." required></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-sm btn-danger fw-semibold">Confirm Rejection</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
