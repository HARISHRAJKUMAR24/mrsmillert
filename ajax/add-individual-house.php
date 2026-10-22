<?php
/* =========================================================
   MRS MILL@ — AJAX: ADD INDIVIDUAL HOUSE
   File: ./ajax/add-individual-house.php
   Accepts password (min 3 chars) → hashed into password_hash.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

/* ---------- READ INPUT ---------- */
$name     = trim($_POST['customer_name'] ?? '');
$mobile   = trim($_POST['mobile_number'] ?? '');
$password = trim($_POST['password'] ?? '');
$address  = trim($_POST['full_address'] ?? '');
$mapLink  = trim($_POST['map_link'] ?? '');
$imgB64   = trim($_POST['house_image_data'] ?? '');

/* ---------- VALIDATION ---------- */
if ($name === '' || mb_strlen($name) < 3) {
    jsonResponse(false, 'Customer name must be at least 3 characters.');
}
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}
if (strlen($password) < 3) {
    jsonResponse(false, 'Password must be at least 3 characters.');
}
if ($address === '') {
    jsonResponse(false, 'Full address is required.');
}
if ($mapLink === '') {
    jsonResponse(false, 'Google Maps location is required.');
}
if (!preg_match('#(google\.[a-z.]+/maps|maps\.google\.|maps\.app\.goo\.gl|goo\.gl/maps)#i', $mapLink)) {
    jsonResponse(false, 'Invalid Google Maps link.');
}

/* ---------- SAVE IMAGE ---------- */
$savedImagePath = '';
if ($imgB64 !== '') {
    if (preg_match('/^data:image\/(png|jpe?g|webp|gif);base64,/i', $imgB64, $m)) {
        $ext  = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imgB64));

        if ($data !== false && strlen($data) <= 3 * 1024 * 1024) {
            $folder = 'uploads/houses/' . date('Ymd') . '/' . bin2hex(random_bytes(5));
            $absDir = rtrim(__DIR__ . '/../', '/\\') . '/' . $folder;
            if (!is_dir($absDir)) @mkdir($absDir, 0775, true);

            $fileName = 'house.' . $ext;
            if (@file_put_contents($absDir . '/' . $fileName, $data) !== false) {
                $savedImagePath = $folder . '/' . $fileName;
            }
        }
    }
}

/* ---------- DUPLICATE MOBILE ---------- */
try {
    $chk = $pdo->prepare("SELECT id FROM individual_houses WHERE mobile_number = ? LIMIT 1");
    $chk->execute([$mobile]);
    if ($chk->fetch()) {
        jsonResponse(false, 'A house with this mobile number already exists.');
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}

/* ---------- HOUSE CODE ---------- */
function generateHouseCode($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT MAX(CAST(SUBSTRING(house_code, 3) AS UNSIGNED)) AS max_num
             FROM individual_houses WHERE house_code LIKE 'IH%'"
        );
        $row  = $stmt->fetch();
        $next = 1;
        if ($row && $row['max_num'] !== null) $next = ((int)$row['max_num']) + 1;
        return 'IH' . str_pad($next, 4, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'IH0001';
    }
}
$code = generateHouseCode($pdo);

/* ---------- HASH PASSWORD ---------- */
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

/* ---------- INSERT ---------- */
try {
    $stmt = $pdo->prepare(
        "INSERT INTO individual_houses
            (house_code, customer_name, mobile_number, password_hash,
             full_address, map_link, house_image, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $stmt->execute([
        $code, $name, $mobile, $passwordHash,
        $address, $mapLink, $savedImagePath
    ]);

    jsonResponse(true, 'Individual house added successfully.', [
        'id'         => (int)$pdo->lastInsertId(),
        'house_code' => $code,
        'image'      => $savedImagePath
    ]);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to add house: ' . $e->getMessage());
}