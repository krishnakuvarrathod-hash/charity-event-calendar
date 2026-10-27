<?php
/**
 * Edit Authorized Charity Event
 * Enforces strict resource ownership so organizers cannot modify other organizations' events.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('organizer');

$eventId = (int)($_GET['id'] ?? 0);
if ($eventId <= 0) {
    header('Location: /organizer/my-events.php');
    exit;
}

$pdo = Database::getConnection();
$userId = current_user_id();

// Verify ownership strictly
if (!verify_event_ownership($pdo, $eventId, $userId)) {
    http_response_code(403);
    set_flash('danger', 'Access denied. You can only edit events belonging to your own charity organization.');
    header('Location: /organizer/my-events.php');
    exit;
}

// Fetch event details
$stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $eventId]);
$event = $stmt->fetch();

if (!$event) {
    set_flash('danger', 'Event not found.');
    header('Location: /organizer/my-events.php');
    exit;
}

$errors = [];
$title = $event['title'];
$description = $event['description'];
$category = $event['category'];
$venueId = (int)$event['venue_id'];
$startDatetime = date('Y-m-d\TH:i', strtotime($event['start_datetime']));
$endDatetime = date('Y-m-d\TH:i', strtotime($event['end_datetime']));
$regDeadline = date('Y-m-d\TH:i', strtotime($event['registration_deadline']));
$capacity = (int)$event['capacity'];
$imageUrl = $event['image_url'] ?? '';

$existingVenues = $pdo->query('SELECT * FROM venues ORDER BY venue_name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Security token invalid or expired. Please resubmit.';
    } else {
        $action = $_POST['form_action'] ?? 'update';

        if ($action === 'cancel_event') {
            $cancelStmt = $pdo->prepare("UPDATE events SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
            $cancelStmt->execute(['id' => $eventId]);
            set_flash('info', 'Your event has been marked as cancelled.');
            header('Location: /organizer/my-events.php');
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $venueId = (int)($_POST['venue_id'] ?? 0);
        $startDatetime = trim($_POST['start_datetime'] ?? '');
        $endDatetime = trim($_POST['end_datetime'] ?? '');
        $regDeadline = trim($_POST['registration_deadline'] ?? '');
        $capacity = max(1, (int)($_POST['capacity'] ?? 50));
        $imageUrl = trim($_POST['image_url'] ?? '');

        // Validation
        if (empty($title)) {
            $errors[] = 'Event title is required.';
        }
        if (empty($description)) {
            $errors[] = 'Event description is required.';
        }
        if (!in_array($category, get_standard_categories(), true)) {
            $errors[] = 'Please select a valid event category.';
        }
        if ($venueId <= 0) {
            $errors[] = 'Please select a valid venue.';
        }
        if (empty($startDatetime) || empty($endDatetime) || empty($regDeadline)) {
            $errors[] = 'All date and deadline fields are required.';
        } else {
            $start = new DateTime($startDatetime);
            $end = new DateTime($endDatetime);
            $deadline = new DateTime($regDeadline);

            if ($end <= $start) {
                $errors[] = 'Event end date & time must be strictly after the start date & time.';
            }
            if ($deadline > $start) {
                $errors[] = 'Registration deadline cannot be after the event start date & time.';
            }
        }

        if (empty($errors)) {
            try {
                // If the event was rejected and the organizer edits it, reset to pending for re-review
                $newStatus = ($event['status'] === 'rejected') ? 'pending' : $event['status'];
                $rejectionReason = ($event['status'] === 'rejected') ? null : $event['rejection_reason'];

                $updateStmt = $pdo->prepare('
                    UPDATE events 
                    SET venue_id = :venue_id, title = :title, description = :description, 
                        category = :category, start_datetime = :start, end_datetime = :end, 
                        registration_deadline = :deadline, capacity = :capacity, image_url = :image_url, 
                        status = :status, rejection_reason = :reason, updated_at = NOW()
                    WHERE id = :id
                ');
                $updateStmt->execute([
                    'venue_id'  => $venueId,
                    'title'     => $title,
                    'description'=> $description,
                    'category'  => $category,
                    'start'     => $startDatetime,
                    'end'       => $endDatetime,
                    'deadline'  => $regDeadline,
                    'capacity'  => $capacity,
                    'image_url' => !empty($imageUrl) ? $imageUrl : null,
                    'status'    => $newStatus,
                    'reason'    => $rejectionReason,
                    'id'        => $eventId,
                ]);

                if ($newStatus === 'pending' && $event['status'] === 'rejected') {
                    set_flash('success', 'Event revised and resubmitted for administrator approval.');
                } else {
                    set_flash('success', 'Event details updated successfully.');
                }

                header('Location: /organizer/my-events.php');
                exit;

            } catch (\Exception $e) {
                error_log('Event update error: ' . $e->getMessage());
                $errors[] = 'A database error occurred while updating the event.';
            }
        }
    }
}

$pageTitle = 'Edit Event: ' . $event['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
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

            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold mb-1">Edit Event</h3>
                        <div class="d-flex align-items-center gap-2">
                            <span>Status:</span> <?= render_status_badge($event['status']) ?>
                        </div>
                    </div>
                    <?php if ($event['status'] !== 'cancelled'): ?>
                        <form method="POST" action="/organizer/edit-event.php?id=<?= $eventId ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" value="cancel_event">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to cancel this event? Cancelled events are no longer publicly joinable.">
                                <i class="bi bi-slash-circle me-1"></i>Cancel Event
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($event['status'] === 'rejected'): ?>
                    <div class="alert alert-danger shadow-sm mb-4">
                        <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Event Was Rejected</h6>
                        <p class="mb-2 small"><strong>Administrator Feedback:</strong> <?= e($event['rejection_reason'] ?: 'No notes provided.') ?></p>
                        <p class="mb-0 small text-muted">Saving edits to this event will automatically resubmit it with "pending" status for re-evaluation.</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/organizer/edit-event.php?id=<?= $eventId ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="update">

                    <div class="mb-3">
                        <label for="title" class="form-label fw-semibold">Event Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" value="<?= e($title) ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <?php foreach (get_standard_categories() as $cat): ?>
                                    <option value="<?= e($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="capacity" class="form-label fw-semibold">Attendee Capacity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="capacity" name="capacity" min="1" max="10000" value="<?= (int)$capacity ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Event Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?= e($description) ?></textarea>
                    </div>

                    <hr class="my-4">

                    <h5 class="fw-bold mb-3">Schedule & Deadlines</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="start_datetime" class="form-label fw-semibold">Start Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="start_datetime" name="start_datetime" value="<?= e($startDatetime) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="end_datetime" class="form-label fw-semibold">End Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="end_datetime" name="end_datetime" value="<?= e($endDatetime) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="registration_deadline" class="form-label fw-semibold">Registration Deadline <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="registration_deadline" name="registration_deadline" value="<?= e($regDeadline) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="venue_id" class="form-label fw-semibold">Venue / Location <span class="text-danger">*</span></label>
                        <select class="form-select" id="venue_id" name="venue_id" required>
                            <?php foreach ($existingVenues as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= ($venueId === (int)$v['id']) ? 'selected' : '' ?>>
                                    <?= e($v['venue_name']) ?> (<?= $v['is_online'] ? 'Virtual Event' : e($v['city'] . ', ' . $v['state']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="image_url" class="form-label fw-semibold">Poster / Cover Image URL</label>
                        <input type="url" class="form-control" id="image_url" name="image_url" value="<?= e($imageUrl) ?>">
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="/organizer/my-events.php" class="btn btn-outline-secondary">Back to My Events</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
