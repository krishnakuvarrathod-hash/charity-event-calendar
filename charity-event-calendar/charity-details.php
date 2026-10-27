<?php
/**
 * Charity Profile & Events Overview
 */
require_once __DIR__ . '/includes/auth.php';

$charityId = (int)($_GET['id'] ?? 0);
if ($charityId <= 0) {
    header('Location: /charities.php');
    exit;
}

$pdo = Database::getConnection();

// Fetch Charity Details
$stmt = $pdo->prepare('
    SELECT c.*, u.full_name AS organizer_name, u.email AS organizer_email
    FROM charities c
    INNER JOIN users u ON c.user_id = u.id
    WHERE c.id = :id
    LIMIT 1
');
$stmt->execute(['id' => $charityId]);
$charity = $stmt->fetch();

if (!$charity) {
    http_response_code(404);
    set_flash('danger', 'The specified charity profile does not exist.');
    header('Location: /charities.php');
    exit;
}

// Fetch Upcoming Approved Events
$upcomingStmt = $pdo->prepare("
    SELECT 
        e.*, 
        v.venue_name, 
        v.city AS venue_city, 
        v.is_online,
        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'confirmed') AS confirmed_registrations
    FROM events e
    INNER JOIN venues v ON e.venue_id = v.id
    WHERE e.charity_id = :id AND e.status = 'approved' AND e.start_datetime >= NOW()
    ORDER BY e.start_datetime ASC
");
$upcomingStmt->execute(['id' => $charityId]);
$upcomingEvents = $upcomingStmt->fetchAll();

// Fetch Past Events
$pastStmt = $pdo->prepare("
    SELECT 
        e.*, 
        v.venue_name, 
        v.city AS venue_city, 
        v.is_online,
        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'confirmed') AS confirmed_registrations
    FROM events e
    INNER JOIN venues v ON e.venue_id = v.id
    WHERE e.charity_id = :id AND e.status = 'approved' AND e.start_datetime < NOW()
    ORDER BY e.start_datetime DESC
    LIMIT 4
");
$pastStmt->execute(['id' => $charityId]);
$pastEvents = $pastStmt->fetchAll();

$pageTitle = $charity['charity_name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="/index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="/charities.php" class="text-decoration-none">Charities</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($charity['charity_name']) ?></li>
        </ol>
    </nav>

    <!-- Charity Header Banner -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white p-4 p-md-5">
        <div class="row align-items-center g-4">
            <div class="col-md-2 text-center text-md-start">
                <div class="avatar-circle bg-primary-subtle text-primary fw-bold mx-auto mx-md-0" style="width: 90px; height: 90px; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem;">
                    <?= strtoupper(substr($charity['charity_name'], 0, 1)) ?>
                </div>
            </div>
            <div class="col-md-10">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($charity['city']) ?>, <?= e($charity['state']) ?>
                    </span>
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-patch-check-fill me-1"></i>Verified Nonprofit
                    </span>
                </div>
                <h1 class="fw-bold mb-2"><?= e($charity['charity_name']) ?></h1>
                <p class="text-secondary lead fs-6 mb-3"><?= e($charity['description']) ?></p>

                <div class="d-flex flex-wrap gap-4 text-muted small pt-2 border-top">
                    <?php if ($charity['address']): ?>
                        <div><i class="bi bi-map me-1 text-primary"></i><?= e($charity['address']) ?></div>
                    <?php endif; ?>
                    <?php if ($charity['email']): ?>
                        <div><i class="bi bi-envelope me-1 text-primary"></i><?= e($charity['email']) ?></div>
                    <?php endif; ?>
                    <?php if ($charity['phone']): ?>
                        <div><i class="bi bi-telephone me-1 text-primary"></i><?= e($charity['phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($charity['website']): ?>
                        <div><i class="bi bi-globe me-1 text-primary"></i><a href="<?= e($charity['website']) ?>" target="_blank" rel="noopener" class="text-decoration-none"><?= e($charity['website']) ?></a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Active & Upcoming Events Section -->
    <div class="mb-5">
        <h4 class="fw-bold mb-3"><i class="bi bi-calendar3-event text-primary me-2"></i>Upcoming Events & Campaigns</h4>
        <?php if (empty($upcomingEvents)): ?>
            <div class="card border-0 shadow-sm p-4 text-center bg-white rounded-4">
                <p class="text-muted mb-0">This charity has no active upcoming events scheduled at this time.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($upcomingEvents as $event): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="event-card">
                            <div class="event-card-img-wrapper">
                                <span class="event-card-category"><?= render_category_badge($event['category']) ?></span>
                                <img src="<?= get_event_image($event['image_url'], $event['category']) ?>" alt="<?= e($event['title']) ?>" class="event-card-img" loading="lazy">
                            </div>
                            <div class="event-card-body">
                                <div class="event-card-meta">
                                    <i class="bi bi-calendar-event"></i>
                                    <span><?= format_datetime($event['start_datetime'], 'M j, Y • g:i A') ?></span>
                                </div>
                                <h3 class="event-card-title">
                                    <a href="/event-details.php?id=<?= (int)$event['id'] ?>" class="text-decoration-none text-dark">
                                        <?= e($event['title']) ?>
                                    </a>
                                </h3>
                                <p class="text-muted small mb-0 flex-grow-1">
                                    <?= e(mb_strimwidth($event['description'], 0, 110, '...')) ?>
                                </p>
                            </div>
                            <div class="event-card-footer">
                                <span class="small text-muted">
                                    <i class="bi bi-people me-1"></i><?= (int)$event['confirmed_registrations'] ?> / <?= (int)$event['capacity'] ?> spots
                                </span>
                                <a href="/event-details.php?id=<?= (int)$event['id'] ?>" class="btn btn-sm btn-primary">
                                    Details <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Past Events Section -->
    <?php if (!empty($pastEvents)): ?>
        <div class="mb-5">
            <h4 class="fw-bold mb-3 text-secondary"><i class="bi bi-clock-history me-2"></i>Past Completed Causes</h4>
            <div class="row g-3">
                <?php foreach ($pastEvents as $past): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm p-3 rounded-3 bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="fw-bold mb-0 text-dark"><?= e($past['title']) ?></h6>
                                <span class="badge bg-secondary"><?= format_date($past['start_datetime']) ?></span>
                            </div>
                            <p class="text-muted small mb-0"><?= e(mb_strimwidth($past['description'], 0, 95, '...')) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
