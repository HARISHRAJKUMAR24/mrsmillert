<?php

// =========================================
// DATABASE CONFIGURATION
// =========================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'mrs_millet');
define('DB_USER', 'root');
define('DB_PASS', '');


// =========================================
// APPLICATION CONFIGURATION
// =========================================

define(
    'ADMIN_URL',
    'http://localhost/mrs.millet.admin/'
);
date_default_timezone_set('Asia/Kolkata');

// =========================================
// START SESSION
// =========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// =========================================
// DATABASE CONNECTION
// =========================================

try {

    $pdo = new PDO(
        "mysql:host=" . DB_HOST .
            ";dbname=" . DB_NAME .
            ";charset=utf8mb4",

        DB_USER,
        DB_PASS
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );
} catch (PDOException $e) {

    die("Database connection failed: " .
        $e->getMessage());
}


// =========================================
// CHECK ADMIN LOGIN
// =========================================

function isLoggedIn()
{
    return isset($_SESSION['admin_id']);
}


// =========================================
// VERIFY ADMIN TOKEN
// =========================================

function verifyToken($pdo)
{
    if (
        !isset($_SESSION['admin_id']) ||
        !isset($_SESSION['admin_token'])
    ) {
        return false;
    }

    try {

        $stmt = $pdo->prepare(
            "SELECT token FROM admin WHERE id = ?"
        );

        $stmt->execute([
            $_SESSION['admin_id']
        ]);

        $admin = $stmt->fetch();

        return (
            $admin &&
            $admin['token'] === $_SESSION['admin_token']
        );
    } catch (PDOException $e) {

        return false;
    }
}


// =========================================
// LOGIN ADMIN  ← NEW
// =========================================

function loginAdmin($pdo, $username, $password)
{
    try {

        $stmt = $pdo->prepare(
            "SELECT id, username, password
             FROM admin
             WHERE username = ?
             LIMIT 1"
        );

        $stmt->execute([$username]);

        $admin = $stmt->fetch();

        if (!$admin) {
            return [
                'success' => false,
                'message' => 'Invalid username or password.'
            ];
        }

        if (!password_verify($password, $admin['password'])) {
            return [
                'success' => false,
                'message' => 'Invalid username or password.'
            ];
        }

        // Generate a fresh token
        $token = bin2hex(random_bytes(32));

        $update = $pdo->prepare(
            "UPDATE admin SET token = ? WHERE id = ?"
        );

        $update->execute([$token, $admin['id']]);

        // Store in session
        $_SESSION['admin_id']    = $admin['id'];
        $_SESSION['admin_name']  = $admin['username'];
        $_SESSION['admin_token'] = $token;

        return [
            'success' => true,
            'message' => 'Login successful.'
        ];
    } catch (PDOException $e) {

        return [
            'success' => false,
            'message' => 'Server error. Please try again.'
        ];
    }
}


// =========================================
// LOGOUT ADMIN  ← NEW
// =========================================

function logoutAdmin($pdo)
{
    if (isset($_SESSION['admin_id'])) {

        try {

            $stmt = $pdo->prepare(
                "UPDATE admin SET token = NULL WHERE id = ?"
            );

            $stmt->execute([$_SESSION['admin_id']]);
        } catch (PDOException $e) {
            // ignore
        }
    }

    $_SESSION = [];

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

    session_destroy();
}


// =========================================
// GET DATA
// =========================================

function getData(
    $column,
    $table,
    $condition
) {
    global $pdo;

    try {

        $stmt = $pdo->prepare(
            "SELECT $column
             FROM $table
             WHERE $condition"
        );

        $stmt->execute();

        $result = $stmt->fetch();

        return $result
            ? $result[$column]
            : '';
    } catch (PDOException $e) {

        return '';
    }
}
