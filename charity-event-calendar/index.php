<?php
/**
 * Homepage - Charity Event Calendar
 * Showcases featured upcoming events, platform statistics, and search quick-links.
 */
$pageTitle = 'Home';
require_once __DIR__ . '/includes/auth.php';

$upcomingEvents = [];
$stats = [
    'events_count' => 0,
    'charities_count' => 0,
    'registrations_count' => 0
];
$dbError = null;

try {
    $pdo = Database::getConnection();

    // 1. Fetch upcoming approved events
    $stmt = $pdo->prepare("
        SELECT 
            e.*, 
            c.charity_name, 
            c.city AS charity_city,
            v.venue_name, 
            v.city AS venue_city, 
            v.is_online,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'confirmed') AS confirmed_registrations
        FROM events e
        INNER JOIN charities c ON e.charity_id = c.id
        INNER JOIN venues v ON e.venue_id = v.id
        WHERE e.status = 'approved' AND e.start_datetime >= NOW()
        ORDER BY e.start_datetime ASC
        LIMIT 6
    ");
    $stmt->execute();
    $upcomingEvents = $stmt->fetchAll();

    // 2. Fetch platform statistics
    $statEvents = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved'")->fetchColumn();
    $statCharities = $pdo->query("SELECT COUNT(*) FROM charities")->fetchColumn();
    $statRegs = $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'confirmed'")->fetchColumn();

    $stats['events_count'] = (int)$statEvents;
    $stats['charities_count'] = (int)$statCharities;
    $stats['registrations_count'] = (int)$statRegs;

} catch (\Throwable $e) {
    error_log("Homepage query error: " . $e->getMessage());
    $dbError = "Database records are currently being updated or connecting. Please check back shortly.";
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section text-center text-lg-start">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="hero-badge">
                    <i class="bi bi-stars text-warning"></i> Connecting Communities & Nonprofits
                </div>
                <h1 class="display-4 fw-bold mb-3">Empowering Generosity, One Event at a Time.</h1>
                <p class="lead mb-4 text-light opacity-90">
                    Discover local fundraisers, food drives, environmental cleanups, and community outreach events. Stand together with passionate organizers creating tangible change.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                    <a href="/events.php" class="btn btn-coral btn-lg px-4 shadow">
                        <i class="bi bi-calendar-event me-2"></i>Explore All Events
                    </a>
                    <a href="/register.php?role=organizer" class="btn btn-outline-light btn-lg px-4">
                        <i class="bi bi-plus-circle me-2"></i>Post Your Charity Event
                    </a>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block text-center">
                <img src="https://images.unsplash.com/photo-1559027615-cd4628902d4a?auto=format&fit=crop&w=700&q=80" 
                     alt="Volunteers smiling together" 
                     class="img-fluid rounded-4 shadow-lg border border-3 border-white/20"
                     style="max-height: 380px; width: 100%; object-fit: cover;">
            </div>
        </div>
    </div>
</section>

<!-- Search Quick-Bar -->
<div class="container" style="margin-top: -1.5rem; position: relative; z-index: 10;">
    <div class="card shadow border-0 rounded-4">
        <div class="card-body p-3 p-md-4">
            <form action="/events.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search event title, charity, or keywords...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">All Event Categories</option>
                        <?php foreach (get_standard_categories() as $cat): ?>
                            <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="period" class="form-select">
                        <option value="upcoming">Upcoming</option>
                        <option value="this_month">This Month</option>
                        <option value="all">Any Date</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-funnel me-1"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Platform Impact Statistics -->
<section class="py-5">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="stat-card justify-content-center">
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="bi bi-calendar2-check-fill"></i>
                    </div>
                    <div class="text-start">
                        <h3 class="fw-bold mb-0"><?= number_format($stats['events_count']) ?>+</h3>
                        <span class="text-muted small">Approved Community Events</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card justify-content-center">
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="bi bi-building-fill"></i>
                    </div>
                    <div class="text-start">
                        <h3 class="fw-bold mb-0"><?= number_format($stats['charities_count']) ?></h3>
                        <span class="text-muted small">Partner Nonprofits</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card justify-content-center">
                    <div class="stat-icon bg-danger-subtle text-danger">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="text-start">
                        <h3 class="fw-bold mb-0"><?= number_format($stats['registrations_count']) ?></h3>
                        <span class="text-muted small">Participant Registrations</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Upcoming Events -->
<section class="py-4 bg-white border-top border-bottom">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="text-uppercase fw-bold text-primary small">Make A Difference</span>
                <h2 class="fw-bold mb-0">Featured Upcoming Events</h2>
            </div>
            <a href="/events.php" class="btn btn-outline-primary fw-medium">
                View All Events <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if ($dbError): ?>
            <div class="alert alert-warning shadow-sm">
                <i class="bi bi-info-circle me-2"></i><?= e($dbError) ?>
            </div>
        <?php elseif (empty($upcomingEvents)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                <h5 class="fw-semibold mt-3">No upcoming events scheduled at the moment</h5>
                <p class="text-muted small">Be the first organizer to publish a community cause!</p>
                <a href="/register.php?role=organizer" class="btn btn-primary mt-2">Publish an Event</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($upcomingEvents as $event): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="event-card">
                            <div class="event-card-img-wrapper">
                                <span class="event-card-category">
                                    <?= render_category_badge($event['category']) ?>
                                </span>
                                <span class="event-card-badge">
                                    <span class="badge bg-dark bg-opacity-75">
                                        <i class="bi bi-geo-alt-fill me-1"></i><?= $event['is_online'] ? 'Virtual' : e($event['venue_city']) ?>
                                    </span>
                                </span>
                                <img src="<?= get_event_image($event['image_url'], $event['category']) ?>" 
                                     alt="<?= e($event['title']) ?>" 
                                     class="event-card-img"
                                     loading="lazy">
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
                                <p class="text-muted small mb-3 flex-grow-1">
                                    <?= e(mb_strimwidth($event['description'], 0, 115, '...')) ?>
                                </p>
                                <div class="event-card-meta mb-0">
                                    <i class="bi bi-heart"></i>
                                    <span>By <a href="/charity-details.php?id=<?= (int)$event['charity_id'] ?>" class="text-decoration-none fw-semibold text-secondary"><?= e($event['charity_name']) ?></a></span>
                                </div>
                            </div>
                            <div class="event-card-footer">
                                <span class="small text-muted">
                                    <i class="bi bi-people me-1"></i><strong><?= (int)$event['confirmed_registrations'] ?></strong> / <?= (int)$event['capacity'] ?> spots
                                </span>
                                <a href="/event-details.php?id=<?= (int)$event['id'] ?>" class="btn btn-sm btn-primary">
                                    View Details <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Category Quick Navigation -->
<section class="py-5">
    <div class="container text-center">
        <h3 class="fw-bold mb-2">Explore by Cause</h3>
        <p class="text-muted mb-4">Find campaigns matching the issues you care about most</p>
        
        <div class="row g-3 justify-content-center">
            <?php
            $catIcons = [
                'Fundraising' => ['icon' => 'bi-currency-dollar', 'color' => 'primary'],
                'Food Donation' => ['icon' => 'bi-basket', 'color' => 'success'],
                'Volunteering' => ['icon' => 'bi-hands', 'color' => 'info'],
                'Health Drive' => ['icon' => 'bi-heart-pulse', 'color' => 'danger'],
                'Community Activity' => ['icon' => 'bi-people', 'color' => 'warning'],
                'Education & Mentorship' => ['icon' => 'bi-book', 'color' => 'secondary']
            ];
            foreach (get_standard_categories() as $category):
                $meta = $catIcons[$category] ?? ['icon' => 'bi-calendar-event', 'color' => 'primary'];
            ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="/events.php?category=<?= urlencode($category) ?>" class="card h-100 text-decoration-none border shadow-sm p-3 text-center transition hover-shadow">
                        <div class="avatar-circle bg-<?= $meta['color'] ?>-subtle text-<?= $meta['color'] ?> mx-auto mb-2" style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                            <i class="bi <?= $meta['icon'] ?>"></i>
                        </div>
                        <span class="fw-semibold text-dark small"><?= e($category) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
