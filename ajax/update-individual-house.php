<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE INDIVIDUAL HOUSE
   File: ./ajax/update-individual-house.php
   Password is optional — only updated when provided.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id          = (int)($_POST['id'] ?? 0);
$name        = trim($_POST['customer_name'] ?? '');
$mobile      = trim($_POST['mobile_number'] ?? '');
$password    = trim($_POST['password'] ?? '');       // optional
$address     = trim($_POST['full_address'] ?? '');
$mapLink     = trim($_POST['map_link'] ?? '');
$imgB64      = trim($_POST['house_image_data'] ?? '');
$removeImage = trim($_POST['remove_image'] ?? '');

/* ---------- Validation ---------- */
if ($id <= 0) jsonResponse(false, 'Invalid house ID.');
if ($name === '' || mb_strlen($name) < 3) jsonResponse(false, 'Customer name must be at least 3 characters.');
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) jsonResponse(false, 'Mobile must be 10–15 digits.');
if ($password !== '' && strlen($password) < 3) jsonResponse(false, 'Password must be at least 3 characters.');
if ($address === '') jsonResponse(false, 'Full address is required.');
if ($mapLink === '') jsonResponse(false, 'Google Maps location is required.');
if (!preg_match('#(google\.[a-z.]+/maps|maps\.google\.|maps\.app\.goo\.gl|goo\.gl/maps)#i', $mapLink)) {
    jsonResponse(false, 'Invalid Google Maps link.');
}

/* ---------- Check exists + current image ---------- */
try {
    $chk = $pdo->prepare("SELECT id, house_code, house_image FROM individual_houses WHERE id = ? LIMIT 1");
    $chk->execute([$id]);
    $existing = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$existing) jsonResponse(false, 'House not found.');

    /* Duplicate mobile check (excluding self) */
    $dup = $pdo->prepare("SELECT id FROM individual_houses WHERE mobile_number = ? AND id <> ? LIMIT 1");
    $dup->execute([$mobile, $id]);
    if ($dup->fetch()) jsonResponse(false, 'Another house already uses this mobile number.');
} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}

/* ---------- Determine final image ---------- */
$finalImage = $existing['house_image'] ?? '';

if ($removeImage === '1') {
    if (!empty($finalImage)) {
        $abs = rtrim(__DIR__ . '/../', '/\\') . '/' . ltrim($finalImage, '/\\');
        if (is_file($abs)) @unlink($abs);
    }
    $finalImage = '';
}

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
                if (!empty($finalImage)) {
                    $oldAbs = rtrim(__DIR__ . '/../', '/\\') . '/' . ltrim($finalImage, '/\\');
                    if (is_file($oldAbs)) @unlink($oldAbs);
                }
                $finalImage = $folder . '/' . $fileName;
            }
        }
    }
}

/* ---------- UPDATE ---------- */
try {
    if ($password !== '') {
        /* Update password too */
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "UPDATE individual_houses
             SET customer_name = ?, mobile_number = ?, password_hash = ?,
                 full_address = ?, map_link = ?, house_image = ?
             WHERE id = ?"
        );
        $stmt->execute([$name, $mobile, $hash, $address, $mapLink, $finalImage, $id]);
    } else {
        /* Keep existing password */
        $stmt = $pdo->prepare(
            "UPDATE individual_houses
             SET customer_name = ?, mobile_number = ?,
                 full_address = ?, map_link = ?, house_image = ?
             WHERE id = ?"
        );
        $stmt->execute([$name, $mobile, $address, $mapLink, $finalImage, $id]);
    }

    jsonResponse(true, 'Individual house updated successfully.', [
        'id'          => $id,
        'house_code'  => $existing['house_code'],
        'house_image' => $finalImage,
        'password_changed' => ($password !== '')
    ]);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to update: ' . $e->getMessage());
}