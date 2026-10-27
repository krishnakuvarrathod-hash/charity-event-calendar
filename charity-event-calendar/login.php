<?php
/**
 * User Login Page
 * Supports visitors, charity organizers, and administrators.
 * Includes rate-limiting protection, CSRF validation, and session fixation defense.
 */
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    $role = current_user_role();
    if ($role === 'admin') {
        header('Location: /admin/dashboard.php');
    } elseif ($role === 'organizer') {
        header('Location: /organizer/dashboard.php');
    } else {
        header('Location: /events.php');
    }
    exit;
}

$pageTitle = 'Sign In';
$errors = [];
$email = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Security token expired. Please try submitting again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $redirect = trim($_POST['redirect'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (empty($email) || empty($password)) {
            $errors[] = 'Please provide both your email address and password.';
        } else {
            try {
                $pdo = Database::getConnection();

                // 1. Check Rate Limiting
                if (is_login_rate_limited($pdo, $ip, $email)) {
                    $errors[] = 'Too many failed login attempts. For security reasons, please wait 15 minutes before trying again.';
                } else {
                    // 2. Fetch User Record
                    $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
                    $stmt->execute(['email' => $email]);
                    $user = $stmt->fetch();

                    // 3. Verify Password
                    if ($user && password_verify($password, $user['password_hash'])) {
                        // Clear login attempts
                        clear_failed_logins($pdo, $ip, $email);

                        // Secure login session
                        login_user($user);

                        set_flash('success', "Welcome back, " . htmlspecialchars($user['full_name']) . "!");

                        // Role-based or query-parameter redirect
                        if (!empty($redirect) && str_starts_with($redirect, '/')) {
                            header("Location: {$redirect}");
                        } elseif ($user['role'] === 'admin') {
                            header('Location: /admin/dashboard.php');
                        } elseif ($user['role'] === 'organizer') {
                            header('Location: /organizer/dashboard.php');
                        } else {
                            header('Location: /events.php');
                        }
                        exit;
                    } else {
                        // Record failed attempt
                        record_failed_login($pdo, $ip, $email);
                        $errors[] = 'Invalid email address or password.';
                    }
                }
            } catch (\Exception $e) {
                error_log('Login error: ' . $e->getMessage());
                $errors[] = 'A database service error occurred. Please try again shortly.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="avatar-circle bg-primary-subtle text-primary mx-auto mb-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <h3 class="fw-bold">Sign In</h3>
                        <p class="text-muted small">Access your Charity Event Calendar account</p>
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

                    <form method="POST" action="/login.php" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= e($email) ?>" placeholder="user@example.org" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="password" class="form-label fw-semibold mb-0">Password</label>
                            </div>
                            <div class="input-group mt-2">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="••••••••" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <span class="text-muted small">Don't have an account?</span>
                        <a href="/register.php" class="small fw-semibold text-primary ms-1">Register Now</a>
                    </div>
                </div>
            </div>

            <!-- Demo Demonstration Credentials Box -->
            <div class="card mt-4 border-0 bg-white shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill text-primary"></i> Academic Demo Credentials
                    </h6>
                    <p class="text-muted small mb-3">All seeded demo accounts share the password: <code>Password@123</code></p>
                    
                    <div class="list-group list-group-flush small">
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-danger me-2">Admin</span>
                                <code>admin@charitycalendar.org</code>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="document.getElementById('email').value='admin@charitycalendar.org'; document.getElementById('password').value='Password@123';">Fill</button>
                        </div>
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-primary me-2">Organizer</span>
                                <code>sarah@hopefoundation.org</code>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="document.getElementById('email').value='sarah@hopefoundation.org'; document.getElementById('password').value='Password@123';">Fill</button>
                        </div>
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-secondary me-2">Visitor</span>
                                <code>john.doe@example.com</code>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="document.getElementById('email').value='john.doe@example.com'; document.getElementById('password').value='Password@123';">Fill</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
