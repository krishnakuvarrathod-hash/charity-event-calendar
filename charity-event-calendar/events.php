<?php
/**
 * Event Catalog - Search, Filtering & Pagination
 */
$pageTitle = 'Explore Events';
require_once __DIR__ . '/includes/auth.php';

$pdo = Database::getConnection();

// Query Filters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$city = trim($_GET['city'] ?? '');
$period = trim($_GET['period'] ?? 'upcoming');
$isOnline = isset($_GET['online']) && $_GET['online'] !== '' ? (int)$_GET['online'] : null;
$myRegistrationsOnly = isset($_GET['my_registrations']) && (int)$_GET['my_registrations'] === 1;

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

// Base query construction
$conditions = [];
$params = [];

// Non-admin public visitors only view approved events
$conditions[] = "e.status = 'approved'";

if (!empty($search)) {
    $conditions[] = "(e.title LIKE :search OR e.description LIKE :search OR c.charity_name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($category)) {
    $conditions[] = "e.category = :category";
    $params['category'] = $category;
}

if (!empty($city)) {
    $conditions[] = "v.city = :city";
    $params['city'] = $city;
}

if ($isOnline !== null) {
    $conditions[] = "v.is_online = :is_online";
    $params['is_online'] = $isOnline;
}

if ($period === 'upcoming') {
    $conditions[] = "e.start_datetime >= NOW()";
} elseif ($period === 'past') {
    $conditions[] = "e.start_datetime < NOW()";
}

// User's own registered events filter
if ($myRegistrationsOnly) {
    require_login();
    $conditions[] = "EXISTS (SELECT 1 FROM registrations r_user WHERE r_user.event_id = e.id AND r_user.user_id = :my_user_id AND r_user.status = 'confirmed')";
    $params['my_user_id'] = current_user_id();
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Count total for pagination
$countSql = "
    SELECT COUNT(DISTINCT e.id)
    FROM events e
    INNER JOIN charities c ON e.charity_id = c.id
    INNER JOIN venues v ON e.venue_id = v.id
    {$whereClause}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalEvents = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalEvents / $perPage);

// Fetch paginated events
$sql = "
    SELECT 
        e.*, 
        c.charity_name, 
        v.venue_name, 
        v.city AS venue_city, 
        v.is_online,
        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'confirmed') AS confirmed_registrations
    FROM events e
    INNER JOIN charities c ON e.charity_id = c.id
    INNER JOIN venues v ON e.venue_id = v.id
    {$whereClause}
    ORDER BY " . ($period === 'past' ? "e.start_datetime DESC" : "e.start_datetime ASC") . "
    LIMIT :per_page OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll();

// Fetch unique cities for filter dropdown
$citiesStmt = $pdo->query("SELECT DISTINCT city FROM venues WHERE is_online = 0 ORDER BY city ASC");
$cities = $citiesStmt->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Header Title -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
        <div>
            <h1 class="fw-bold mb-1">
                <?= $myRegistrationsOnly ? '<i class="bi bi-ticket-perforated-fill text-primary me-2"></i>My Event Registrations' : 'Explore Charity Events' ?>
            </h1>
            <p class="text-muted small mb-0">
                <?= $myRegistrationsOnly ? 'Events you have confirmed attendance for' : 'Discover local causes, volunteering drives, and charity fundraisers' ?>
            </p>
        </div>
        <div>
            <?php if ($myRegistrationsOnly): ?>
                <a href="/events.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to All Events
                </a>
            <?php elseif (is_logged_in()): ?>
                <a href="/events.php?my_registrations=1" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-ticket-perforated me-1"></i>View My Registrations
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card mb-4">
        <form method="GET" action="/events.php" class="row g-3">
            <?php if ($myRegistrationsOnly): ?>
                <input type="hidden" name="my_registrations" value="1">
            <?php endif; ?>

            <div class="col-lg-4 col-md-6">
                <label for="search" class="form-label small fw-semibold text-muted">Keyword or Charity</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Search titles, causes...">
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <label for="category" class="form-label small fw-semibold text-muted">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach (get_standard_categories() as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-4">
                <label for="city" class="form-label small fw-semibold text-muted">Location / City</label>
                <select class="form-select" id="city" name="city">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?= e($c) ?>" <?= ($city === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-4">
                <label for="period" class="form-label small fw-semibold text-muted">Time Frame</label>
                <select class="form-select" id="period" name="period">
                    <option value="upcoming" <?= ($period === 'upcoming') ? 'selected' : '' ?>>Upcoming Events</option>
                    <option value="past" <?= ($period === 'past') ? 'selected' : '' ?>>Past Events</option>
                    <option value="all" <?= ($period === 'all') ? 'selected' : '' ?>>All Dates</option>
                </select>
            </div>

            <div class="col-lg-1 col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter"></i>
                </button>
            </div>
        </form>

        <?php if (!empty($search) || !empty($category) || !empty($city) || $period !== 'upcoming'): ?>
            <div class="mt-3 pt-2 border-top d-flex align-items-center gap-2">
                <span class="small text-muted">Active filters:</span>
                <?php if (!empty($search)): ?>
                    <span class="badge bg-light text-dark border">"<?= e($search) ?>"</span>
                <?php endif; ?>
                <?php if (!empty($category)): ?>
                    <span class="badge bg-light text-dark border"><?= e($category) ?></span>
                <?php endif; ?>
                <?php if (!empty($city)): ?>
                    <span class="badge bg-light text-dark border"><?= e($city) ?></span>
                <?php endif; ?>
                <a href="/events.php<?= $myRegistrationsOnly ? '?my_registrations=1' : '' ?>" class="small text-danger text-decoration-none ms-auto">
                    <i class="bi bi-x-circle me-1"></i>Reset All
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Results Count -->
    <div class="d-flex justify-content-between align-items-center mb-3 text-muted small">
        <span>Found <strong><?= $totalEvents ?></strong> event<?= $totalEvents === 1 ? '' : 's' ?></span>
        <?php if ($totalPages > 1): ?>
            <span>Page <?= $page ?> of <?= $totalPages ?></span>
        <?php endif; ?>
    </div>

    <!-- Events Grid -->
    <?php if (empty($events)): ?>
        <div class="card border-0 shadow-sm py-5 text-center my-4">
            <div class="card-body">
                <div class="avatar-circle bg-light text-muted mx-auto mb-3" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                    <i class="bi bi-calendar-x"></i>
                </div>
                <h5 class="fw-bold">No Events Found</h5>
                <p class="text-muted small mb-3">No charity events matched your selected criteria.</p>
                <a href="/events.php" class="btn btn-sm btn-outline-primary">Clear Filters</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-5">
            <?php foreach ($events as $event): ?>
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
                                <?= e(mb_strimwidth($event['description'], 0, 120, '...')) ?>
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

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Event pagination" class="d-flex justify-content-center">
                <ul class="pagination">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
