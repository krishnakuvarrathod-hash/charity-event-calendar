<?php
/**
 * Charities Directory Page
 * Search and browse registered nonprofit organizations.
 */
$pageTitle = 'Partner Charities';
require_once __DIR__ . '/includes/auth.php';

$pdo = Database::getConnection();

$search = trim($_GET['search'] ?? '');
$city = trim($_GET['city'] ?? '');

$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(c.charity_name LIKE :search OR c.description LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($city)) {
    $conditions[] = "c.city = :city";
    $params['city'] = $city;
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "
    SELECT 
        c.*,
        u.full_name AS organizer_name,
        (SELECT COUNT(*) FROM events e WHERE e.charity_id = c.id AND e.status = 'approved' AND e.start_datetime >= NOW()) AS active_events_count
    FROM charities c
    INNER JOIN users u ON c.user_id = u.id
    {$whereClause}
    ORDER BY c.charity_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$charities = $stmt->fetchAll();

// Fetch distinct cities for filtering
$cityStmt = $pdo->query("SELECT DISTINCT city FROM charities ORDER BY city ASC");
$cities = $cityStmt->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
        <div>
            <h1 class="fw-bold mb-1">Partner Charities & Nonprofits</h1>
            <p class="text-muted small mb-0">Discover verified nonprofit partners organizing impactful causes in your area</p>
        </div>
        <div>
            <?php if (is_logged_in() && has_role('organizer')): ?>
                <a href="/organizer/profile.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-gear me-1"></i>Manage My Charity Profile
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="filter-card mb-4">
        <form method="GET" action="/charities.php" class="row g-3">
            <div class="col-md-7">
                <label for="search" class="form-label small fw-semibold text-muted">Charity Name or Mission</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Search charity name or keywords...">
                </div>
            </div>
            <div class="col-md-3">
                <label for="city" class="form-label small fw-semibold text-muted">City</label>
                <select class="form-select" id="city" name="city">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?= e($c) ?>" <?= ($city === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Charities Grid -->
    <?php if (empty($charities)): ?>
        <div class="card border-0 shadow-sm py-5 text-center my-4">
            <div class="card-body">
                <div class="avatar-circle bg-light text-muted mx-auto mb-3" style="width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                    <i class="bi bi-building"></i>
                </div>
                <h5 class="fw-bold">No Charities Found</h5>
                <p class="text-muted small mb-3">No registered charities matched your search parameters.</p>
                <a href="/charities.php" class="btn btn-sm btn-outline-primary">Reset Filter</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-5">
            <?php foreach ($charities as $charity): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 transition hover-shadow">
                        <div class="card-body p-4 d-flex flex-direction-column flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="avatar-circle bg-primary-subtle text-primary fw-bold" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                                    <?= strtoupper(substr($charity['charity_name'], 0, 1)) ?>
                                </div>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($charity['city']) ?>, <?= e($charity['state']) ?>
                                </span>
                            </div>

                            <h5 class="fw-bold mb-2">
                                <a href="/charity-details.php?id=<?= (int)$charity['id'] ?>" class="text-decoration-none text-dark">
                                    <?= e($charity['charity_name']) ?>
                                </a>
                            </h5>

                            <p class="text-muted small mb-4 flex-grow-1">
                                <?= e(mb_strimwidth($charity['description'], 0, 140, '...')) ?>
                            </p>

                            <div class="border-top pt-3 mt-auto d-flex align-items-center justify-content-between">
                                <span class="badge bg-success-subtle text-success">
                                    <i class="bi bi-calendar-event me-1"></i><?= (int)$charity['active_events_count'] ?> active event<?= $charity['active_events_count'] === 1 ? '' : 's' ?>
                                </span>
                                <a href="/charity-details.php?id=<?= (int)$charity['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    View Causes <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
