<?php
/**
 * Create New Charity Event
 * Submits an event under the organizer's charity profile with 'pending' approval status.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('organizer');

$pdo = Database::getConnection();
$userId = current_user_id();

// Verify charity profile exists
$charity = get_organizer_charity($pdo, $userId);
if (!$charity) {
    set_flash('warning', 'Please set up your charity profile before publishing events.');
    header('Location: /organizer/profile.php');
    exit;
}

$errors = [];
$title = '';
$description = '';
$category = 'Fundraising';
$venueOption = 'existing';
$venueId = 0;
$startDatetime = '';
$endDatetime = '';
$regDeadline = '';
$capacity = 50;
$imageUrl = '';

// New venue fields if creating new
$newVenueName = '';
$newVenueAddress = '';
$newVenueCity = '';
$newVenueState = '';
$newVenuePostal = '';
$newVenueIsOnline = 0;

// Fetch existing venues for selection
$existingVenues = $pdo->query('SELECT * FROM venues ORDER BY venue_name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Security token invalid or expired. Please resubmit.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $venueOption = $_POST['venue_option'] ?? 'existing';
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
        if (empty($startDatetime) || empty($endDatetime) || empty($regDeadline)) {
            $errors[] = 'Start date, end date, and registration deadline are all required.';
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

        // Venue resolution
        if ($venueOption === 'new') {
            $newVenueName = trim($_POST['new_venue_name'] ?? '');
            $newVenueIsOnline = isset($_POST['new_venue_online']) ? 1 : 0;
            $newVenueAddress = trim($_POST['new_venue_address'] ?? '');
            $newVenueCity = $newVenueIsOnline ? 'Online' : trim($_POST['new_venue_city'] ?? '');
            $newVenueState = $newVenueIsOnline ? 'Virtual' : trim($_POST['new_venue_state'] ?? '');
            $newVenuePostal = $newVenueIsOnline ? '00000' : trim($_POST['new_venue_postal'] ?? '');

            if (empty($newVenueName)) {
                $errors[] = 'New venue name is required.';
            }
            if (empty($newVenueAddress)) {
                $errors[] = 'Venue address or virtual stream URL is required.';
            }
            if (!$newVenueIsOnline && (empty($newVenueCity) || empty($newVenueState))) {
                $errors[] = 'City and State are required for physical venues.';
            }
        } else {
            if ($venueId <= 0) {
                $errors[] = 'Please select a valid venue from the list or choose to add a new venue.';
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // If adding new venue, insert it first
                if ($venueOption === 'new') {
                    $venueStmt = $pdo->prepare('
                        INSERT INTO venues (venue_name, address, city, state, postal_code, is_online)
                        VALUES (:name, :address, :city, :state, :postal, :online)
                    ');
                    $venueStmt->execute([
                        'name'    => $newVenueName,
                        'address' => $newVenueAddress,
                        'city'    => $newVenueCity,
                        'state'   => $newVenueState,
                        'postal'  => $newVenuePostal,
                        'online'  => $newVenueIsOnline,
                    ]);
                    $venueId = (int)$pdo->lastInsertId();
                }

                // Insert event with pending status
                $eventStmt = $pdo->prepare('
                    INSERT INTO events (charity_id, venue_id, title, description, category, start_datetime, end_datetime, registration_deadline, capacity, image_url, status)
                    VALUES (:charity_id, :venue_id, :title, :description, :category, :start, :end, :deadline, :capacity, :image_url, :status)
                ');
                $eventStmt->execute([
                    'charity_id' => $charity['id'],
                    'venue_id'   => $venueId,
                    'title'      => $title,
                    'description'=> $description,
                    'category'   => $category,
                    'start'      => $startDatetime,
                    'end'        => $endDatetime,
                    'deadline'   => $regDeadline,
                    'capacity'   => $capacity,
                    'image_url'  => !empty($imageUrl) ? $imageUrl : null,
                    'status'     => 'pending',
                ]);

                $pdo->commit();

                set_flash('success', 'Your event submission has been created successfully and is now pending administrator approval.');
                header('Location: /organizer/dashboard.php');
                exit;

            } catch (\Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Event creation error: ' . $e->getMessage());
                $errors[] = 'A database error occurred while creating your event. Please try again.';
            }
        }
    }
}

$pageTitle = 'Create Charity Event';
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
                    <a class="nav-link" href="/organizer/my-events.php"><i class="bi bi-calendar3 me-1"></i>My Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/organizer/profile.php"><i class="bi bi-building me-1"></i>Charity Profile</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/organizer/registrations.php"><i class="bi bi-people me-1"></i>Registrations</a>
                </li>
            </ul>

            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="avatar-circle bg-coral-subtle text-danger" style="width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background-color: #fee2e2;">
                        <i class="bi bi-calendar-plus-fill"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0">Publish New Charity Event</h3>
                        <p class="text-muted small mb-0">Submitting on behalf of <strong><?= e($charity['charity_name']) ?></strong></p>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/organizer/create-event.php" id="eventForm">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="title" class="form-label fw-semibold">Event Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg fs-6" id="title" name="title" value="<?= e($title) ?>" placeholder="e.g. Annual 5K Run for Children Education" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label fw-semibold">Event Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <?php foreach (get_standard_categories() as $cat): ?>
                                    <option value="<?= e($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="capacity" class="form-label fw-semibold">Attendee Capacity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="capacity" name="capacity" min="1" max="10000" value="<?= (int)$capacity ?>" required>
                            <div class="form-text small">Maximum confirmed participants allowed</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Event Description & Objectives <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" placeholder="Detail the activities, items to bring, volunteer duties, and where donations go..." required><?= e($description) ?></textarea>
                    </div>

                    <hr class="my-4">

                    <!-- Dates & Deadlines -->
                    <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Date & Timeline</h5>
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

                    <hr class="my-4">

                    <!-- Venue Strategy -->
                    <h5 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Location & Venue</h5>
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="venue_option" id="venue_existing" value="existing" <?= ($venueOption === 'existing') ? 'checked' : '' ?> onclick="toggleVenueSection()">
                            <label class="form-check-label fw-semibold" for="venue_existing">Choose Existing Venue</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="venue_option" id="venue_new" value="new" <?= ($venueOption === 'new') ? 'checked' : '' ?> onclick="toggleVenueSection()">
                            <label class="form-check-label fw-semibold" for="venue_new">Add a New Venue / Stream Link</label>
                        </div>
                    </div>

                    <!-- Existing Venue Dropdown -->
                    <div id="existing_venue_container" class="mb-3 <?= ($venueOption === 'new') ? 'd-none' : '' ?>">
                        <label for="venue_id" class="form-label fw-semibold">Select Venue</label>
                        <select class="form-select" id="venue_id" name="venue_id">
                            <option value="">-- Choose a location --</option>
                            <?php foreach ($existingVenues as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= ($venueId === (int)$v['id']) ? 'selected' : '' ?>>
                                    <?= e($v['venue_name']) ?> (<?= $v['is_online'] ? 'Virtual Event' : e($v['city'] . ', ' . $v['state']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- New Venue Inputs -->
                    <div id="new_venue_container" class="p-3 bg-light rounded-3 mb-3 <?= ($venueOption === 'existing') ? 'd-none' : '' ?>">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="new_venue_online" name="new_venue_online" value="1" <?= $newVenueIsOnline ? 'checked' : '' ?> onchange="toggleOnlineVenueFields()">
                            <label class="form-check-label fw-semibold" for="new_venue_online">
                                This is a Virtual / Online Event
                            </label>
                        </div>

                        <div class="mb-3">
                            <label for="new_venue_name" class="form-label fw-semibold">Venue / Platform Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_venue_name" name="new_venue_name" value="<?= e($newVenueName) ?>" placeholder="e.g. Austin Civic Center or Zoom Webinar">
                        </div>

                        <div class="mb-3">
                            <label for="new_venue_address" class="form-label fw-semibold">Address or Video Stream URL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_venue_address" name="new_venue_address" value="<?= e($newVenueAddress) ?>" placeholder="e.g. 500 E 4th St or https://zoom.us/j/...">
                        </div>

                        <div id="physical_venue_fields" class="row g-3 <?= $newVenueIsOnline ? 'd-none' : '' ?>">
                            <div class="col-md-5">
                                <label for="new_venue_city" class="form-label fw-semibold">City</label>
                                <input type="text" class="form-control" id="new_venue_city" name="new_venue_city" value="<?= e($newVenueCity) ?>" placeholder="e.g. Austin">
                            </div>
                            <div class="col-md-4">
                                <label for="new_venue_state" class="form-label fw-semibold">State / Province</label>
                                <input type="text" class="form-control" id="new_venue_state" name="new_venue_state" value="<?= e($newVenueState) ?>" placeholder="e.g. TX">
                            </div>
                            <div class="col-md-3">
                                <label for="new_venue_postal" class="form-label fw-semibold">Postal Code</label>
                                <input type="text" class="form-control" id="new_venue_postal" name="new_venue_postal" value="<?= e($newVenuePostal) ?>" placeholder="78701">
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Media / Poster -->
                    <div class="mb-4">
                        <label for="image_url" class="form-label fw-semibold">Cover Image URL (Optional)</label>
                        <input type="url" class="form-control" id="image_url" name="image_url" value="<?= e($imageUrl) ?>" placeholder="https://images.unsplash.com/photo-...">
                        <div class="form-text small">Leave blank to use an automated, category-matching photography placeholder.</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="/organizer/dashboard.php" class="btn btn-outline-secondary">Discard</a>
                        <button type="submit" class="btn btn-coral px-4 fw-semibold">
                            <i class="bi bi-send-check-fill me-1"></i> Submit Event for Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleVenueSection() {
    const isNew = document.getElementById('venue_new').checked;
    document.getElementById('existing_venue_container').classList.toggle('d-none', isNew);
    document.getElementById('new_venue_container').classList.toggle('d-none', !isNew);
}
function toggleOnlineVenueFields() {
    const isOnline = document.getElementById('new_venue_online').checked;
    document.getElementById('physical_venue_fields').classList.toggle('d-none', isOnline);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
