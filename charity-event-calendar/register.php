<?php
/**
 * User Registration Page
 * Allows visitors and charity organizers to create accounts.
 */
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$pageTitle = 'Create Account';
$errors = [];
$name = '';
$email = '';
$role = 'visitor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Invalid or expired session security token. Please try again.';
    } else {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirm'] ?? '';
        $role = trim($_POST['role'] ?? 'visitor');

        // Validation
        if (empty($name)) {
            $errors[] = 'Full name is required.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }
        if (!in_array($role, ['visitor', 'organizer'], true)) {
            $errors[] = 'Invalid account role selected.';
        }

        if (empty($errors)) {
            try {
                $pdo = Database::getConnection();

                // Check email uniqueness
                $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $checkStmt->execute(['email' => $email]);
                if ($checkStmt->fetch()) {
                    $errors[] = 'An account with this email address already exists. Please log in.';
                } else {
                    // Hash password and insert
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $insertStmt = $pdo->prepare('
                        INSERT INTO users (full_name, email, password_hash, role) 
                        VALUES (:name, :email, :hash, :role)
                    ');
                    $insertStmt->execute([
                        'name'  => $name,
                        'email' => $email,
                        'hash'  => $hash,
                        'role'  => $role,
                    ]);

                    $newUserId = (int)$pdo->lastInsertId();

                    // Log the user in automatically
                    login_user([
                        'id'        => $newUserId,
                        'full_name' => $name,
                        'email'     => $email,
                        'role'      => $role,
                    ]);

                    set_flash('success', "Welcome to Charity Event Calendar, {$name}! Your account was created successfully.");

                    if ($role === 'organizer') {
                        header('Location: /organizer/profile.php');
                    } else {
                        header('Location: /events.php');
                    }
                    exit;
                }
            } catch (\Exception $e) {
                error_log('Registration error: ' . $e->getMessage());
                $errors[] = 'An unexpected error occurred while creating your account. Please try again.';
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
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <h3 class="fw-bold">Create an Account</h3>
                        <p class="text-muted small">Join our community to discover, volunteer, or publish charity events</p>
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

                    <form method="POST" action="/register.php" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control border-start-0" id="full_name" name="full_name" value="<?= e($name) ?>" placeholder="e.g. Sarah Jenkins" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= e($email) ?>" placeholder="name@example.org" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">I want to join as: <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check flex-fill p-3 border rounded-3 bg-light">
                                    <input class="form-check-input" type="radio" name="role" id="role_visitor" value="visitor" <?= ($role === 'visitor') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-medium" for="role_visitor">
                                        <i class="bi bi-heart text-danger me-1"></i> Community Visitor
                                        <div class="text-muted small fw-normal">Browse and register for events</div>
                                    </label>
                                </div>
                                <div class="form-check flex-fill p-3 border rounded-3 bg-light">
                                    <input class="form-check-input" type="radio" name="role" id="role_organizer" value="organizer" <?= ($role === 'organizer') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-medium" for="role_organizer">
                                        <i class="bi bi-building text-primary me-1"></i> Charity Organizer
                                        <div class="text-muted small fw-normal">Publish & manage fundraisers</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="At least 8 characters" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirm" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" class="form-control border-start-0" id="password_confirm" name="password_confirm" placeholder="Repeat your password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-person-check-fill me-1"></i> Complete Registration
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <span class="text-muted small">Already registered?</span>
                        <a href="/login.php" class="small fw-semibold text-primary ms-1">Sign In Here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
