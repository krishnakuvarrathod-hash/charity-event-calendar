<?php
/**
 * User Logout Page
 */
require_once __DIR__ . '/includes/auth.php';

logout_user();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash('info', 'You have been safely signed out. See you soon!');
header('Location: /login.php');
exit;
