<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE APARTMENT
   File: ./ajax/update-apartment.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id           = (int) ($_POST['id'] ?? 0);
$branchId     = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int) $_POST['branch_id'] : null;
$name         = trim($_POST['apartment_name'] ?? '');
$address      = trim($_POST['apartment_address'] ?? '');
$status       = isset($_POST['status']) ? (int) $_POST['status'] : 1;
$divisionsRaw = $_POST['divisions'] ?? '[]';

$divisions = json_decode($divisionsRaw, true);
if (!is_array($divisions)) $divisions = [];

if ($id <= 0)       jsonResponse(false, 'Invalid apartment ID.');
if ($name === '')   jsonResponse(false, 'Apartment name is required.');

$cleanDivisions = [];
$seen           = [];

foreach ($divisions as $d) {
    $block  = isset($d['block'])   ? trim((string) $d['block'])   : '';
    $doorNo = isset($d['door_no']) ? trim((string) $d['door_no']) : '';
    $floor  = isset($d['floor'])   ? trim((string) $d['floor'])   : '';
    $charge = isset($d['charge'])  ? trim((string) $d['charge'])  : '0';

    // Skip completely empty rows
    if ($block === '' && $doorNo === '' && $floor === '') {
        continue;
    }

    // No strict validation on block/door/floor
    if (!is_numeric($charge) || (float) $charge < 0) jsonResponse(false, 'Each variant must have a valid delivery charge.');

    $key = strtolower($block . '|' . $doorNo . '|' . $floor);
    if (isset($seen[$key])) {
        jsonResponse(false, 'Duplicate variant: Block "' . $block . '", Door No "' . $doorNo . '", Floor "' . $floor . '".');
    }
    $seen[$key] = true;

    $cleanDivisions[] = [
        'block'   => $block,
        'door_no' => $doorNo,
        'floor'   => $floor,
        'charge'  => (float) $charge
    ];
}

$divisionsJson = json_encode($cleanDivisions, JSON_UNESCAPED_UNICODE);

try {
    $check = $pdo->prepare("SELECT id, apartment_code FROM apartments WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    $existing = $check->fetch();

    if (!$existing) jsonResponse(false, 'Apartment not found.');

    if ($branchId !== null) {
        $branchCheck = $pdo->prepare("SELECT id FROM settings_branches WHERE id = ? LIMIT 1");
        $branchCheck->execute([$branchId]);
        if (!$branchCheck->fetch()) jsonResponse(false, 'Selected branch does not exist.');
    }

    if ($branchId !== null) {
        $dup = $pdo->prepare("SELECT id FROM apartments WHERE apartment_name = ? AND branch_id = ? AND id <> ? LIMIT 1");
        $dup->execute([$name, $branchId, $id]);
    } else {
        $dup = $pdo->prepare("SELECT id FROM apartments WHERE apartment_name = ? AND branch_id IS NULL AND id <> ? LIMIT 1");
        $dup->execute([$name, $id]);
    }

    if ($dup->fetch()) jsonResponse(false, 'Another apartment already uses this name in this branch.');

} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}

try {
    $stmt = $pdo->prepare(
        "UPDATE apartments
         SET apartment_name    = ?,
             apartment_address = ?,
             branch_id         = ?,
             divisions         = ?,
             status            = ?
         WHERE id = ?"
    );

    $stmt->execute([$name, $address, $branchId, $divisionsJson, $status, $id]);

    jsonResponse(true, 'Apartment updated successfully.', [
        'id'        => $id,
        'code'      => $existing['apartment_code'],
        'branch_id' => $branchId,
        'divisions' => $cleanDivisions
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to update apartment.');
}