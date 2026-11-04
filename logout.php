<?php
require_once './config/config.php';
require_once './config/function.php';

// 1. Clear the token in the database if admin is logged in
if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE admin SET token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
    } catch (PDOException $e) {
        // Ignore DB errors during logout
    }
}

// 2. Clear all session variables
$_SESSION = array();

// 3. Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 4. Destroy the session
session_destroy();

// 5. Redirect to login page
header('Location: login.php');
exit;