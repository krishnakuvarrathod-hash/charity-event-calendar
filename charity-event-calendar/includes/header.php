<?php
/**
 * Global Header Component
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$currentUser = current_user();
$flashes = get_flashes();

// If admin is logged in, optionally fetch count of pending events for navbar badge
$pendingCount = 0;
if (has_role('admin')) {
    try {
        $pdoBadge = Database::getConnection();
        $stmtBadge = $pdoBadge->query("SELECT COUNT(*) FROM events WHERE status = 'pending'");
        $pendingCount = (int)$stmtBadge->fetchColumn();
    } catch (\Throwable $e) {
        $pendingCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | Charity Event Calendar' : 'Charity Event Calendar - Discover & Support Local Causes' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Application CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top py-3">
    <div class="container">
        <a class="navbar-brand" href="/index.php">
            <i class="bi bi-heart-pulse-fill"></i>
            <span>Charity Calendar</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : '' ?>" href="/index.php">
                        <i class="bi bi-house-door me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'events.php') ? 'active' : '' ?>" href="/events.php">
                        <i class="bi bi-calendar3 me-1"></i>Explore Events
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'charities.php') ? 'active' : '' ?>" href="/charities.php">
                        <i class="bi bi-building me-1"></i>Charities
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-2">
                <?php if ($currentUser): ?>
                    <?php if (has_role('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-pill-admin" href="/admin/dashboard.php">
                                <i class="bi bi-shield-lock-fill me-1"></i>Admin Panel
                                <?php if ($pendingCount > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-1"><?= $pendingCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php elseif (has_role('organizer')): ?>
                        <li class="nav-item">
                            <a class="nav-link nav-pill-organizer" href="/organizer/dashboard.php">
                                <i class="bi bi-grid-fill me-1"></i>Organizer Hub
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-sm btn-coral" href="/organizer/create-event.php">
                                <i class="bi bi-plus-circle me-1"></i>Create Event
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- User Dropdown Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar-circle bg-light border text-dark fw-bold px-2 py-1 rounded-circle">
                                <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                            </span>
                            <span class="fw-medium text-dark"><?= e($currentUser['full_name']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li class="dropdown-header">
                                Signed in as <strong><?= e($currentUser['email']) ?></strong><br>
                                <small class="text-uppercase text-muted"><?= e($currentUser['role']) ?></small>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (has_role('organizer')): ?>
                                <li><a class="dropdown-item" href="/organizer/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="/organizer/my-events.php"><i class="bi bi-calendar-check me-2"></i>My Events</a></li>
                                <li><a class="dropdown-item" href="/organizer/profile.php"><i class="bi bi-building me-2"></i>Charity Profile</a></li>
                                <li><a class="dropdown-item" href="/organizer/registrations.php"><i class="bi bi-people me-2"></i>Registrations</a></li>
                            <?php elseif (has_role('admin')): ?>
                                <li><a class="dropdown-item" href="/admin/dashboard.php"><i class="bi bi-speedometer me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="/admin/pending-events.php"><i class="bi bi-hourglass-split me-2"></i>Pending Approvals</a></li>
                                <li><a class="dropdown-item" href="/admin/manage-events.php"><i class="bi bi-calendar-event me-2"></i>All Events</a></li>
                                <li><a class="dropdown-item" href="/admin/manage-charities.php"><i class="bi bi-building me-2"></i>Manage Charities</a></li>
                                <li><a class="dropdown-item" href="/admin/manage-users.php"><i class="bi bi-people me-2"></i>Manage Users</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="/events.php?my_registrations=1"><i class="bi bi-ticket-perforated me-2"></i>My Registrations</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-primary" href="/register.php">
                            <i class="bi bi-person-plus me-1"></i>Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash Alerts Container -->
<div class="container mt-3">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?php if ($flash['type'] === 'success'): ?>
                <i class="bi bi-check-circle-fill me-2"></i>
            <?php elseif ($flash['type'] === 'danger'): ?>
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php elseif ($flash['type'] === 'warning'): ?>
                <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?php else: ?>
                <i class="bi bi-info-circle-fill me-2"></i>
            <?php endif; ?>
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>
</div>
