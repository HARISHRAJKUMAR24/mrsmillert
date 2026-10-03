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
// CHECK LOGIN (admin OR staff)
// =========================================

function isLoggedIn()
{
    return isset($_SESSION['admin_id']);
}


// =========================================
// ROLE CHECK — ADMIN ONLY
// =========================================

function isAdmin()
{
    return isset($_SESSION['admin_role']) &&
           $_SESSION['admin_role'] === 'admin';
}


// =========================================
// ROLE CHECK — STAFF
// =========================================

function isStaff()
{
    return isset($_SESSION['admin_role']) &&
           $_SESSION['admin_role'] === 'staff';
}


// =========================================
// REQUIRE ADMIN ROLE
// Redirects to index.php if not admin
// =========================================

function requireAdmin()
{
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}


// =========================================
// VERIFY TOKEN (admin OR staff table)
// =========================================

function verifyToken($pdo)
{
    if (
        !isset($_SESSION['admin_id']) ||
        !isset($_SESSION['admin_token'])
    ) {
        return false;
    }

    $role = $_SESSION['admin_role'] ?? 'admin';

    try {

        if ($role === 'admin') {

            /* First, look in admin table */
            $stmt = $pdo->prepare(
                "SELECT token FROM admin WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$_SESSION['admin_id']]);
            $row = $stmt->fetch();

            if ($row && $row['token'] === $_SESSION['admin_token']) {
                return true;
            }

            /* Fallback: could also be a staff member with admin role */
            $stmt = $pdo->prepare(
                "SELECT token FROM staff WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$_SESSION['admin_id']]);
            $row = $stmt->fetch();

            return (bool)($row && $row['token'] === $_SESSION['admin_token']);
        }

        /* Staff role */
        $stmt = $pdo->prepare(
            "SELECT token FROM staff WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$_SESSION['admin_id']]);
        $row = $stmt->fetch();

        return (bool)($row && $row['token'] === $_SESSION['admin_token']);

    } catch (PDOException $e) {

        return false;
    }
}


// =========================================
// LOGIN ADMIN (admin table only)
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

        /* Fresh token */
        $token = bin2hex(random_bytes(32));

        $update = $pdo->prepare(
            "UPDATE admin SET token = ? WHERE id = ?"
        );

        $update->execute([$token, $admin['id']]);

        /* Session */
        $_SESSION['admin_id']    = $admin['id'];
        $_SESSION['admin_name']  = $admin['username'];
        $_SESSION['admin_token'] = $token;
        $_SESSION['admin_role']  = 'admin';     /* ← IMPORTANT */

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
// LOGIN STAFF (staff table)
// Accepts mobile OR email as username
// =========================================

function loginStaff($pdo, $username, $password)
{
    try {

        $stmt = $pdo->prepare(
            "SELECT id, staff_code, full_name, mobile_number,
                    email_address, password_hash, role, status
             FROM staff
             WHERE (mobile_number = ? OR email_address = ?)
             LIMIT 1"
        );

        $stmt->execute([$username, $username]);

        $staff = $stmt->fetch();

        if (!$staff) {
            return [
                'success' => false,
                'message' => 'Invalid username or password.'
            ];
        }

        if ((int)$staff['status'] !== 1) {
            return [
                'success' => false,
                'message' => 'Your account is inactive. Contact admin.'
            ];
        }

        if (!password_verify($password, $staff['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Invalid username or password.'
            ];
        }

        /* Fresh token */
        $token = bin2hex(random_bytes(32));

        $update = $pdo->prepare(
            "UPDATE staff
             SET token = ?, last_login_at = NOW()
             WHERE id = ?"
        );

        $update->execute([$token, $staff['id']]);

        /* Session */
        $_SESSION['admin_id']    = (int)$staff['id'];
        $_SESSION['admin_name']  = $staff['full_name'];
        $_SESSION['admin_token'] = $token;
        $_SESSION['admin_role']  = $staff['role'];   /* 'admin' or 'staff' */

        return [
            'success' => true,
            'message' => 'Login successful.',
            'role'    => $staff['role']
        ];

    } catch (PDOException $e) {

        return [
            'success' => false,
            'message' => 'Server error. Please try again.'
        ];
    }
}


// =========================================
// LOGOUT (admin OR staff)
// =========================================

function logoutAdmin($pdo)
{
    if (isset($_SESSION['admin_id'])) {

        $role = $_SESSION['admin_role'] ?? 'admin';

        try {

            if ($role === 'admin') {

                /* Clear in admin table */
                $stmt = $pdo->prepare(
                    "UPDATE admin SET token = NULL WHERE id = ?"
                );
                $stmt->execute([$_SESSION['admin_id']]);

                /* Also try staff table (in case it's a staff-admin) */
                try {
                    $stmt = $pdo->prepare(
                        "UPDATE staff SET token = NULL WHERE id = ?"
                    );
                    $stmt->execute([$_SESSION['admin_id']]);
                } catch (PDOException $e) { /* ignore */ }

            } else {

                $stmt = $pdo->prepare(
                    "UPDATE staff SET token = NULL WHERE id = ?"
                );
                $stmt->execute([$_SESSION['admin_id']]);
            }

        } catch (PDOException $e) {
            /* ignore */
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

function getData($column, $table, $condition)
{
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