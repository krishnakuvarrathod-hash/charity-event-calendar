<?php
/**
 * Organizer Charity Profile Management
 * Create or update the organizer's affiliated charity organization.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('organizer');

$pdo = Database::getConnection();
$userId = current_user_id();

// Fetch existing charity profile
$charity = get_organizer_charity($pdo, $userId);

$errors = [];
$name = $charity['charity_name'] ?? '';
$description = $charity['description'] ?? '';
$email = $charity['email'] ?? current_user()['email'];
$phone = $charity['phone'] ?? '';
$website = $charity['website'] ?? '';
$address = $charity['address'] ?? '';
$city = $charity['city'] ?? '';
$state = $charity['state'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Invalid or expired session security token. Please resubmit.';
    } else {
        $name = trim($_POST['charity_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');

        if (empty($name)) {
            $errors[] = 'Charity / Organization name is required.';
        }
        if (empty($description)) {
            $errors[] = 'Please provide an overview or mission statement for your charity.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid contact email address is required.';
        }
        if (empty($city) || empty($state)) {
            $errors[] = 'City and State are required.';
        }
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            $errors[] = 'If provided, website must be a valid URL (including http:// or https://).';
        }

        if (empty($errors)) {
            try {
                if ($charity) {
                    // Update
                    $stmt = $pdo->prepare('
                        UPDATE charities 
                        SET charity_name = :name, description = :description, email = :email, 
                            phone = :phone, website = :website, address = :address, city = :city, 
                            state = :state, updated_at = NOW()
                        WHERE id = :id AND user_id = :user_id
                    ');
                    $stmt->execute([
                        'name'        => $name,
                        'description' => $description,
                        'email'       => $email,
                        'phone'       => $phone,
                        'website'     => $website,
                        'address'     => $address,
                        'city'        => $city,
                        'state'       => $state,
                        'id'          => $charity['id'],
                        'user_id'     => $userId
                    ]);
                    set_flash('success', 'Charity organization profile updated successfully.');
                } else {
                    // Insert
                    $stmt = $pdo->prepare('
                        INSERT INTO charities (user_id, charity_name, description, email, phone, website, address, city, state)
                        VALUES (:user_id, :name, :description, :email, :phone, :website, :address, :city, :state)
                    ');
                    $stmt->execute([
                        'user_id'     => $userId,
                        'name'        => $name,
                        'description' => $description,
                        'email'       => $email,
                        'phone'       => $phone,
                        'website'     => $website,
                        'address'     => $address,
                        'city'        => $city,
                        'state'       => $state
                    ]);
                    set_flash('success', 'Charity organization profile established successfully! You can now publish events.');
                }

                header('Location: /organizer/dashboard.php');
                exit;

            } catch (\Exception $e) {
                error_log('Charity profile save error: ' . $e->getMessage());
                $errors[] = 'A database error occurred while saving your profile. Please try again.';
            }
        }
    }
}

$pageTitle = 'Manage Charity Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Navigation pill tabs -->
            <ul class="nav nav-pills mb-4 gap-2">
                <li class="nav-item">
                    <a class="nav-link" href="/organizer/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/organizer/my-events.php"><i class="bi bi-calendar3 me-1"></i>My Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="/organizer/profile.php"><i class="bi bi-building me-1"></i>Charity Profile</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/organizer/registrations.php"><i class="bi bi-people me-1"></i>Registrations</a>
                </li>
            </ul>

            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="avatar-circle bg-primary-subtle text-primary" style="width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="bi bi-building"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0"><?= $charity ? 'Edit Charity Organization' : 'Create Charity Organization' ?></h3>
                        <p class="text-muted small mb-0">Nonprofit profile displayed on your public event listings</p>
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

                <form method="POST" action="/organizer/profile.php">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="charity_name" class="form-label fw-semibold">Charity / Nonprofit Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="charity_name" name="charity_name" value="<?= e($name) ?>" placeholder="e.g. Hope Children Foundation" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Mission Statement & Overview <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="4" placeholder="Describe the mission, community focus, and history of your nonprofit..." required><?= e($description) ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Official Contact Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" placeholder="contact@charity.org" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="+1 (555) 012-3456">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="website" class="form-label fw-semibold">Official Website</label>
                        <input type="url" class="form-control" id="website" name="website" value="<?= e($website) ?>" placeholder="https://www.examplecharity.org">
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label fw-semibold">Street Address / Headquarters</label>
                        <input type="text" class="form-control" id="address" name="address" value="<?= e($address) ?>" placeholder="123 Community Lane, Suite 400">
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="city" class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="city" name="city" value="<?= e($city) ?>" placeholder="e.g. Austin" required>
                        </div>
                        <div class="col-md-6">
                            <label for="state" class="form-label fw-semibold">State / Province <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="state" name="state" value="<?= e($state) ?>" placeholder="e.g. TX" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="/organizer/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Charity Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
