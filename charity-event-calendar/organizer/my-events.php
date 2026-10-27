<?php
/**
 * Organizer Events Management
 * View, filter, and cancel events submitted by the logged-in organizer.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('organizer');

$pdo = Database::getConnection();
$userId = current_user_id();

$charity = get_organizer_charity($pdo, $userId);
$events = [];
$filterStatus = $_GET['status'] ?? 'all';

// Handle quick cancellation action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_event') {
    if (!validate_csrf()) {
        set_flash('danger', 'Security validation failed.');
    } else {
        $cancelId = (int)($_POST['event_id'] ?? 0);
        if (verify_event_ownership($pdo, $cancelId, $userId)) {
            $stmtCancel = $pdo->prepare("UPDATE events SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
            $stmtCancel->execute(['id' => $cancelId]);
            set_flash('success', 'Event successfully marked as cancelled.');
        } else {
            set_flash('danger', 'Unauthorized operation.');
        }
    }
    header('Location: /organizer/my-events.php?status=' . urlencode($filterStatus));
    exit;
}

if ($charity) {
    $charityId = (int)$charity['id'];
    $sql = "
        SELECT 
            e.*, 
            v.venue_name, 
            v.city AS venue_city, 
            v.is_online,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'confirmed') AS confirmed_registrations
        FROM events e
        INNER JOIN venues v ON e.venue_id = v.id
        WHERE e.charity_id = :cid
    ";

    $params = ['cid' => $charityId];

    if ($filterStatus !== 'all' && in_array($filterStatus, ['approved', 'pending', 'rejected', 'cancelled'], true)) {
        $sql .= " AND e.status = :status";
        $params['status'] = $filterStatus;
    }

    $sql .= " ORDER BY e.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
}

$pageTitle = 'My Published Events';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Navigation pill tabs -->
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link" href="/organizer/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="/organizer/my-events.php"><i class="bi bi-calendar3 me-1"></i>My Events</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/organizer/profile.php"><i class="bi bi-building me-1"></i>Charity Profile</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/organizer/registrations.php"><i class="bi bi-people me-1"></i>Registrations</a>
        </li>
    </ul>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="fw-bold mb-1">My Events</h1>
            <p class="text-muted small mb-0">Manage all campaign submissions and track live attendance</p>
        </div>
        <div>
            <?php if ($charity): ?>
                <a href="/organizer/create-event.php" class="btn btn-coral">
                    <i class="bi bi-plus-circle me-1"></i> Publish New Event
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status filter tabs -->
    <div class="mb-4">
        <div class="btn-group" role="group">
            <a href="?status=all" class="btn btn-sm <?= ($filterStatus === 'all') ? 'btn-dark' : 'btn-outline-secondary' ?>">All Events</a>
            <a href="?status=approved" class="btn btn-sm <?= ($filterStatus === 'approved') ? 'btn-success' : 'btn-outline-secondary' ?>">Approved</a>
            <a href="?status=pending" class="btn btn-sm <?= ($filterStatus === 'pending') ? 'btn-warning' : 'btn-outline-secondary' ?>">Pending Review</a>
            <a href="?status=rejected" class="btn btn-sm <?= ($filterStatus === 'rejected') ? 'btn-danger' : 'btn-outline-secondary' ?>">Rejected</a>
            <a href="?status=cancelled" class="btn btn-sm <?= ($filterStatus === 'cancelled') ? 'btn-secondary' : 'btn-outline-secondary' ?>">Cancelled</a>
        </div>
    </div>

    <?php if (empty($events)): ?>
        <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-4">
            <div class="avatar-circle bg-light text-muted mx-auto mb-3" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                <i class="bi bi-calendar-x"></i>
            </div>
            <h5 class="fw-bold">No events found</h5>
            <p class="text-muted small mb-3">No events match the selected status filter.</p>
            <?php if ($charity): ?>
                <a href="/organizer/create-event.php" class="btn btn-sm btn-primary">Create an Event</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Title & Venue</th>
                            <th>Category</th>
                            <th>Event Timing</th>
                            <th>RSVP Count</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <a href="/event-details.php?id=<?= (int)$event['id'] ?>" class="text-decoration-none text-dark">
                                            <?= e($event['title']) ?>
                                        </a>
                                    </div>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= $event['is_online'] ? 'Virtual Event' : e($event['venue_city']) ?></small>
                                </td>
                                <td><?= render_category_badge($event['category']) ?></td>
                                <td>
                                    <div class="small fw-semibold"><?= format_datetime($event['start_datetime'], 'M j, Y') ?></div>
                                    <div class="text-muted small"><?= format_time($event['start_datetime']) ?></div>
                                </td>
                                <td>
                                    <a href="/organizer/registrations.php?event_id=<?= (int)$event['id'] ?>" class="text-decoration-none">
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-people me-1"></i><?= (int)$event['confirmed_registrations'] ?> / <?= (int)$event['capacity'] ?>
                                        </span>
                                    </a>
                                </td>
                                <td>
                                    <?= render_status_badge($event['status']) ?>
                                    <?php if ($event['status'] === 'rejected' && !empty($event['rejection_reason'])): ?>
                                        <div class="text-danger small mt-1" style="max-width: 200px;">
                                            <i class="bi bi-info-circle me-1"></i><?= e($event['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Manage
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="/event-details.php?id=<?= (int)$event['id'] ?>">
                                                    <i class="bi bi-eye me-2"></i>View Public Page
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="/organizer/edit-event.php?id=<?= (int)$event['id'] ?>">
                                                    <i class="bi bi-pencil me-2"></i>Edit Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="/organizer/registrations.php?event_id=<?= (int)$event['id'] ?>">
                                                    <i class="bi bi-people me-2"></i>View Attendee List
                                                </a>
                                            </li>
                                            <?php if ($event['status'] !== 'cancelled'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="/organizer/my-events.php" class="px-2">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="cancel_event">
                                                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                                                        <button type="submit" class="dropdown-item text-danger rounded py-1" data-confirm="Are you sure you want to cancel this event?">
                                                            <i class="bi bi-slash-circle me-2"></i>Cancel Event
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
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
