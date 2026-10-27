<?php
/**
 * Event Registrations Attendee Roster
 * View participants registered for the organizer's charity events.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('organizer');

$pdo = Database::getConnection();
$userId = current_user_id();

$charity = get_organizer_charity($pdo, $userId);
$selectedEventId = (int)($_GET['event_id'] ?? 0);

$organizerEvents = [];
$registrations = [];

if ($charity) {
    $charityId = (int)$charity['id'];

    // Fetch list of events for the dropdown filter
    $stmtEvents = $pdo->prepare('SELECT id, title FROM events WHERE charity_id = :cid ORDER BY start_datetime DESC');
    $stmtEvents->execute(['cid' => $charityId]);
    $organizerEvents = $stmtEvents->fetchAll();

    // Query registrations
    $sql = "
        SELECT 
            r.*, 
            u.full_name AS attendee_name, 
            u.email AS attendee_email, 
            e.title AS event_title,
            e.start_datetime AS event_start
        FROM registrations r
        INNER JOIN users u ON r.user_id = u.id
        INNER JOIN events e ON r.event_id = e.id
        WHERE e.charity_id = :cid
    ";

    $params = ['cid' => $charityId];

    if ($selectedEventId > 0) {
        $sql .= " AND e.id = :eid";
        $params['eid'] = $selectedEventId;
    }

    $sql .= " ORDER BY r.registration_date DESC";

    $stmtRegs = $pdo->prepare($sql);
    $stmtRegs->execute($params);
    $registrations = $stmtRegs->fetchAll();
}

$pageTitle = 'Attendee Registrations';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
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
            <a class="nav-link active" href="/organizer/registrations.php"><i class="bi bi-people me-1"></i>Registrations</a>
        </li>
    </ul>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="fw-bold mb-1">Attendee Registrations</h1>
            <p class="text-muted small mb-0">Roster of participants who registered for your charity events</p>
        </div>
        <div>
            <form method="GET" action="/organizer/registrations.php" class="d-flex align-items-center gap-2">
                <select name="event_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All My Events</option>
                    <?php foreach ($organizerEvents as $ev): ?>
                        <option value="<?= (int)$ev['id'] ?>" <?= ($selectedEventId === (int)$ev['id']) ? 'selected' : '' ?>>
                            <?= e(mb_strimwidth($ev['title'], 0, 40, '...')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if (empty($registrations)): ?>
        <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-4">
            <div class="avatar-circle bg-light text-muted mx-auto mb-3" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                <i class="bi bi-person-x"></i>
            </div>
            <h5 class="fw-bold">No Registrations Yet</h5>
            <p class="text-muted small mb-0">When visitors RSVP to your events, their attendance information will appear here.</p>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Attendee</th>
                            <th>Event Name</th>
                            <th>Event Date</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $r): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($r['attendee_name']) ?></div>
                                    <small class="text-muted"><?= e($r['attendee_email']) ?></small>
                                </td>
                                <td>
                                    <a href="/event-details.php?id=<?= (int)$r['event_id'] ?>" class="text-decoration-none fw-semibold text-primary">
                                        <?= e($r['event_title']) ?>
                                    </a>
                                </td>
                                <td>
                                    <small><?= format_datetime($r['event_start'], 'M j, Y • g:i A') ?></small>
                                </td>
                                <td>
                                    <small class="text-muted"><?= format_datetime($r['registration_date']) ?></small>
                                </td>
                                <td>
                                    <?= render_status_badge($r['status']) ?>
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
