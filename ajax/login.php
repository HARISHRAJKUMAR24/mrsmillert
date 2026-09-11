<?php
/* =========================================================
   MRS MILL@ — AJAX LOGIN ENDPOINT
   File: ./ajax/login.php
   Returns JSON: { success: bool, message: string }
   ========================================================= */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* -----------------------------------------
   ONLY POST ALLOWED
----------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

/* -----------------------------------------
   READ INPUT
----------------------------------------- */

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

/* -----------------------------------------
   BASIC VALIDATION
----------------------------------------- */

if ($username === '' || $password === '') {

    echo json_encode([
        'success' => false,
        'message' => 'Please enter username and password.'
    ]);
    exit;
}

/* -----------------------------------------
   CALL CONFIG HELPER
----------------------------------------- */

$result = loginAdmin($pdo, $username, $password);

/* -----------------------------------------
   RESPOND
----------------------------------------- */

echo json_encode([
    'success' => !empty($result['success']),
    'message' => $result['message']
        ?? ($result['success']
            ? 'Login successful.'
            : 'Login failed.')
]);
exit;