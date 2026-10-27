<?php
/**
 * Safe Administrator Initialization CLI Script
 * Run this script to establish or reset the root administrator account.
 * Usage:
 *   php scripts/init-admin.php [email] [password] [fullName]
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Forbidden: This script can only be executed via the command line interface (CLI).\n");
}

require_once __DIR__ . '/../config/database.php';

echo "========================================================\n";
echo " Charity Event Calendar - Administrator Initialization\n";
echo "========================================================\n\n";

try {
    $pdo = Database::getConnection();
} catch (\Throwable $e) {
    echo "ERROR: Failed to connect to MySQL database.\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

// Arguments or interactive prompt
$email = $argv[1] ?? null;
$password = $argv[2] ?? null;
$fullName = $argv[3] ?? 'System Administrator';

if (empty($email)) {
    echo "Enter Administrator Email Address [admin@charitycalendar.org]: ";
    $line = trim(fgets(STDIN));
    $email = !empty($line) ? $line : 'admin@charitycalendar.org';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "ERROR: Invalid email address format.\n";
    exit(1);
}

if (empty($password)) {
    echo "Enter Administrator Password (leave blank to auto-generate random 16-character password): ";
    $line = trim(fgets(STDIN));
    if (!empty($line)) {
        $password = $line;
    } else {
        $password = bin2hex(random_bytes(8)) . '!A1'; // Guaranteed high entropy password
        echo "Auto-generated secure password: {$password}\n";
    }
}

if (strlen($password) < 8) {
    echo "ERROR: Password must be at least 8 characters long.\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $check = $pdo->prepare('SELECT id, full_name, role FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);
    $existing = $check->fetch();

    if ($existing) {
        $update = $pdo->prepare('UPDATE users SET full_name = :name, password_hash = :hash, role = "admin", updated_at = NOW() WHERE id = :id');
        $update->execute([
            'name' => $fullName,
            'hash' => $hash,
            'id'   => $existing['id']
        ]);
        echo "\nSUCCESS: Existing account for '{$email}' updated to Administrator role with new password.\n";
    } else {
        $insert = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (:name, :email, :hash, "admin")');
        $insert->execute([
            'name'  => $fullName,
            'email' => $email,
            'hash'  => $hash
        ]);
        echo "\nSUCCESS: New Administrator account created successfully!\n";
    }

    echo "Email:    {$email}\n";
    echo "Role:     admin\n";
    echo "Password: (hidden for security)\n";
    echo "========================================================\n";

} catch (\Throwable $e) {
    echo "ERROR: Failed to save administrator account: " . $e->getMessage() . "\n";
    exit(1);
}
