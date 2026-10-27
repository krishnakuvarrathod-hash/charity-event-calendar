<?php
/**
 * Event Details & Registration Management
 * Full event specifications, organizer details, venue info, and transactional registration.
 */
require_once __DIR__ . '/includes/auth.php';

$eventId = (int)($_GET['id'] ?? 0);
if ($eventId <= 0) {
    header('Location: /events.php');
    exit;
}

$pdo = Database::getConnection();

// Fetch Event Details
$stmt = $pdo->prepare('
    SELECT 
        e.*, 
        c.charity_name, 
        c.description AS charity_description,
        c.email AS charity_email,
        c.phone AS charity_phone,
        c.website AS charity_website,
        c.user_id AS charity_owner_id,
        v.venue_name, 
        v.address AS venue_address,
        v.city AS venue_city, 
        v.state AS venue_state,
        v.postal_code AS venue_postal_code,
        v.is_online
    FROM events e
    INNER JOIN charities c ON e.charity_id = c.id
    INNER JOIN venues v ON e.venue_id = v.id
    WHERE e.id = :id
    LIMIT 1
');
$stmt->execute(['id' => $eventId]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    set_flash('danger', 'The requested charity event does not exist or has been removed.');
    header('Location: /events.php');
    exit;
}

$userId = current_user_id();
$isOrganizer = is_logged_in() && ($event['charity_owner_id'] == $userId);
$isAdmin = has_role('admin');

// Non-approved events can only be viewed by the owning organizer or an admin
if ($event['status'] !== 'approved' && !$isOrganizer && !$isAdmin) {
    http_response_code(403);
    set_flash('warning', 'This event is currently pending review and is not accessible to the public.');
    header('Location: /events.php');
    exit;
}

// Fetch registration counts
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = :id AND status = 'confirmed'");
$countStmt->execute(['id' => $eventId]);
$confirmedCount = (int)$countStmt->fetchColumn();
$availableSpots = max(0, (int)$event['capacity'] - $confirmedCount);

// Check if current user is registered
$userRegistration = null;
if ($userId) {
    $regCheck = $pdo->prepare('SELECT * FROM registrations WHERE event_id = :event_id AND user_id = :user_id LIMIT 1');
    $regCheck->execute(['event_id' => $eventId, 'user_id' => $userId]);
    $userRegistration = $regCheck->fetch();
}

$isRegistered = ($userRegistration && $userRegistration['status'] === 'confirmed');
$now = new DateTime();
$startDt = new DateTime($event['start_datetime']);
$deadlineDt = new DateTime($event['registration_deadline']);
$isPastEvent = ($startDt <= $now);
$isDeadlinePassed = ($deadlineDt < $now);

// ----------------------------------------------------
// Handle Registration / Cancellation POST Actions
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();

    if (!validate_csrf()) {
        set_flash('danger', 'Security validation failed. Please try again.');
        header("Location: /event-details.php?id={$eventId}");
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        if ($event['status'] !== 'approved') {
            set_flash('danger', 'Registration is unavailable because this event is not active.');
        } elseif ($isPastEvent) {
            set_flash('danger', 'This event has already started or concluded.');
        } elseif ($isDeadlinePassed) {
            set_flash('danger', 'The registration deadline for this event has passed.');
        } else {
            // Transactional capacity check to prevent concurrent race condition overbooking
            try {
                $pdo->beginTransaction();

                // Lock the event row for update
                $lockStmt = $pdo->prepare('SELECT capacity, status FROM events WHERE id = :id FOR UPDATE');
                $lockStmt->execute(['id' => $eventId]);
                $lockedEvent = $lockStmt->fetch();

                if (!$lockedEvent || $lockedEvent['status'] !== 'approved') {
                    $pdo->rollBack();
                    set_flash('danger', 'This event is no longer accepting registrations.');
                    header("Location: /event-details.php?id={$eventId}");
                    exit;
                }

                // Check confirmed count under transaction lock
                $lockCount = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = :id AND status = 'confirmed' FOR UPDATE");
                $lockCount->execute(['id' => $eventId]);
                $currentConfirmed = (int)$lockCount->fetchColumn();

                if ($currentConfirmed >= (int)$lockedEvent['capacity']) {
                    $pdo->rollBack();
                    set_flash('warning', 'Sorry, this event has reached full capacity.');
                    header("Location: /event-details.php?id={$eventId}");
                    exit;
                }

                // Insert or reactivate registration
                $insertStmt = $pdo->prepare("
                    INSERT INTO registrations (event_id, user_id, registration_date, status)
                    VALUES (:event_id, :user_id, NOW(), 'confirmed')
                    ON DUPLICATE KEY UPDATE status = 'confirmed', registration_date = NOW()
                ");
                $insertStmt->execute([
                    'event_id' => $eventId,
                    'user_id'  => $userId
                ]);

                $pdo->commit();
                set_flash('success', 'Congratulations! You have successfully registered for this event.');
                header("Location: /event-details.php?id={$eventId}");
                exit;

            } catch (\Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Registration exception: " . $e->getMessage());
                set_flash('danger', 'An error occurred while confirming your registration. Please try again.');
            }
        }
    } elseif ($action === 'cancel_registration') {
        if ($userRegistration) {
            $cancelStmt = $pdo->prepare("UPDATE registrations SET status = 'cancelled' WHERE id = :id AND user_id = :user_id");
            $cancelStmt->execute([
                'id'      => $userRegistration['id'],
                'user_id' => $userId
            ]);
            set_flash('info', 'Your registration for this event has been cancelled.');
            header("Location: /event-details.php?id={$eventId}");
            exit;
        }
    }

    header("Location: /event-details.php?id={$eventId}");
    exit;
}

$pageTitle = $event['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb & Back -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="/events.php" class="text-decoration-none">Events</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e(mb_strimwidth($event['title'], 0, 35, '...')) ?></li>
        </ol>
    </nav>

    <!-- Administrative / Organizer Status Banners -->
    <?php if ($event['status'] === 'pending'): ?>
        <div class="alert alert-warning border-warning shadow-sm d-flex align-items-center justify-content-between mb-4">
            <div>
                <i class="bi bi-hourglass-split me-2 fs-5"></i>
                <strong>Pending Approval:</strong> This event submission is currently under review by an administrator.
            </div>
            <?php if ($isAdmin): ?>
                <a href="/admin/pending-events.php" class="btn btn-sm btn-warning">Review in Admin Panel</a>
            <?php endif; ?>
        </div>
    <?php elseif ($event['status'] === 'rejected'): ?>
        <div class="alert alert-danger border-danger shadow-sm mb-4">
            <div class="fw-bold mb-1"><i class="bi bi-x-octagon-fill me-2"></i>Event Rejected by Administrator</div>
            <p class="mb-0 small"><strong>Reason:</strong> <?= e($event['rejection_reason'] ?: 'Please contact site administrator for details.') ?></p>
        </div>
    <?php elseif ($event['status'] === 'cancelled'): ?>
        <div class="alert alert-secondary border-secondary shadow-sm mb-4">
            <i class="bi bi-info-circle me-2"></i><strong>Notice:</strong> This event has been marked as cancelled by the organizer.
        </div>
    <?php endif; ?>

    <!-- Event Hero Banner -->
    <div class="event-hero-banner shadow">
        <img src="<?= get_event_image($event['image_url'], $event['category']) ?>" alt="<?= e($event['title']) ?>">
        <div class="event-hero-overlay">
            <div class="w-100">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?= render_category_badge($event['category']) ?>
                    <?= render_status_badge($event['status']) ?>
                    <?php if ($event['is_online']): ?>
                        <span class="badge bg-info text-dark"><i class="bi bi-camera-video me-1"></i>Virtual Event</span>
                    <?php else: ?>
                        <span class="badge bg-light text-dark"><i class="bi bi-geo-alt me-1"></i><?= e($event['venue_city']) ?>, <?= e($event['venue_state']) ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="display-6 fw-bold mb-1 text-white"><?= e($event['title']) ?></h1>
                <p class="mb-0 text-white-50">Organized by <strong class="text-white"><?= e($event['charity_name']) ?></strong></p>
            </div>
        </div>
    </div>

    <!-- Main Content & Sidebar -->
    <div class="row g-4">
        <!-- Left: Event Information -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                <h4 class="fw-bold mb-3 text-dark">About This Event</h4>
                <div class="lead fs-6 text-secondary mb-4" style="line-height: 1.8; white-space: pre-line;">
                    <?= e($event['description']) ?>
                </div>

                <hr class="my-4 text-muted">

                <h5 class="fw-bold mb-3"><i class="bi bi-calendar-range text-primary me-2"></i>Date & Schedule</h5>
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3">
                            <span class="text-muted small d-block">Starts On</span>
                            <strong class="text-dark"><?= format_datetime($event['start_datetime']) ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-3">
                            <span class="text-muted small d-block">Ends On</span>
                            <strong class="text-dark"><?= format_datetime($event['end_datetime']) ?></strong>
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold mb-3"><i class="bi bi-geo-alt text-danger me-2"></i>Location & Venue</h5>
                <div class="p-3 bg-light rounded-3 mb-2">
                    <?php if ($event['is_online']): ?>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-info text-dark">Online Virtual Event</span>
                        </div>
                        <p class="text-muted mb-0 small">Access Link / Platform: <?= e($event['venue_address']) ?></p>
                    <?php else: ?>
                        <h6 class="fw-bold text-dark mb-1"><?= e($event['venue_name']) ?></h6>
                        <p class="text-muted mb-0 small">
                            <?= e($event['venue_address']) ?><br>
                            <?= e($event['venue_city']) ?>, <?= e($event['venue_state']) ?> <?= e($event['venue_postal_code']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Host Charity Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0">Hosted by <?= e($event['charity_name']) ?></h5>
                    <a href="/charity-details.php?id=<?= (int)$event['charity_id'] ?>" class="btn btn-sm btn-outline-primary">
                        View Profile <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <p class="text-muted small mb-3"><?= e($event['charity_description']) ?></p>
                <div class="d-flex flex-wrap gap-4 text-muted small">
                    <?php if ($event['charity_email']): ?>
                        <div><i class="bi bi-envelope text-primary me-1"></i><?= e($event['charity_email']) ?></div>
                    <?php endif; ?>
                    <?php if ($event['charity_phone']): ?>
                        <div><i class="bi bi-telephone text-primary me-1"></i><?= e($event['charity_phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($event['charity_website']): ?>
                        <div><i class="bi bi-globe text-primary me-1"></i><a href="<?= e($event['charity_website']) ?>" target="_blank" rel="noopener" class="text-decoration-none"><?= e($event['charity_website']) ?></a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Registration Box & Controls -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top bg-white" style="top: 90px;">
                <h5 class="fw-bold mb-3 text-dark">Registration Status</h5>

                <!-- Capacity Progress Bar -->
                <?php 
                $percent = $event['capacity'] > 0 ? min(100, round(($confirmedCount / $event['capacity']) * 100)) : 0;
                $barColor = $percent >= 100 ? 'bg-danger' : ($percent >= 80 ? 'bg-warning' : 'bg-primary');
                ?>
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Capacity: <strong><?= $confirmedCount ?></strong> / <?= (int)$event['capacity'] ?></span>
                    <span><?= $percent ?>% full</span>
                </div>
                <div class="progress mb-3" style="height: 10px;">
                    <div class="progress-bar <?= $barColor ?>" role="progressbar" style="width: <?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>

                <div class="p-3 bg-light rounded-3 mb-4 small text-secondary">
                    <div class="mb-1">
                        <i class="bi bi-clock-history me-1 text-danger"></i>
                        <strong>Deadline:</strong> <?= format_datetime($event['registration_deadline']) ?>
                    </div>
                    <?php if ($availableSpots > 0 && !$isPastEvent && !$isDeadlinePassed): ?>
                        <div class="text-success fw-medium">
                            <i class="bi bi-check-circle me-1"></i><strong><?= $availableSpots ?></strong> spots remaining
                        </div>
                    <?php elseif ($availableSpots <= 0): ?>
                        <div class="text-danger fw-medium">
                            <i class="bi bi-slash-circle me-1"></i>Event is currently at full capacity
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Registration Action Forms -->
                <?php if ($isRegistered): ?>
                    <div class="alert alert-success border-success text-center mb-3">
                        <i class="bi bi-patch-check-fill fs-4 d-block mb-1"></i>
                        <span class="fw-semibold">You are registered!</span>
                        <div class="small text-muted mt-1">Confirmed on <?= format_datetime($userRegistration['registration_date']) ?></div>
                    </div>
                    <form method="POST" action="/event-details.php?id=<?= $eventId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel_registration">
                        <button type="submit" class="btn btn-outline-danger w-100" data-confirm="Are you sure you want to cancel your confirmed registration for this event?">
                            <i class="bi bi-x-circle me-1"></i>Cancel My Registration
                        </button>
                    </form>
                <?php elseif ($isPastEvent): ?>
                    <button class="btn btn-secondary w-100 py-2" disabled>
                        <i class="bi bi-calendar-x me-1"></i>Event Has Ended
                    </button>
                <?php elseif ($isDeadlinePassed): ?>
                    <button class="btn btn-secondary w-100 py-2" disabled>
                        <i class="bi bi-hourglass-bottom me-1"></i>Deadline Passed
                    </button>
                <?php elseif ($availableSpots <= 0): ?>
                    <button class="btn btn-secondary w-100 py-2" disabled>
                        <i class="bi bi-people-fill me-1"></i>Fully Booked
                    </button>
                <?php elseif ($event['status'] !== 'approved'): ?>
                    <button class="btn btn-warning w-100 py-2" disabled>
                        <i class="bi bi-hourglass-split me-1"></i>Pending Review
                    </button>
                <?php elseif (!is_logged_in()): ?>
                    <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-coral w-100 py-2 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Sign In to Register
                    </a>
                    <div class="text-center mt-2">
                        <a href="/register.php" class="small text-muted text-decoration-none">New here? Create free account</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="/event-details.php?id=<?= $eventId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="register">
                        <button type="submit" class="btn btn-coral w-100 py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i>Register for Event
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Organizer / Admin Direct Action Links -->
                <?php if ($isOrganizer || $isAdmin): ?>
                    <hr class="my-4">
                    <span class="text-uppercase small fw-bold text-muted d-block mb-2">Management Controls</span>
                    <div class="d-grid gap-2">
                        <a href="/organizer/edit-event.php?id=<?= $eventId ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil me-1"></i>Edit This Event
                        </a>
                        <?php if ($isAdmin): ?>
                            <a href="/admin/manage-events.php" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-shield me-1"></i>Admin Event Manager
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
