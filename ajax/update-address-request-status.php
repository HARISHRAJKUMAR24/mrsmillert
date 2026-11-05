<?php
/* =========================================================
   MRS MILL@ — UPDATE ADDRESS REQUEST (Approve / Unapprove / Reject)
   File: ./ajax/update-address-request-status.php   v2026-11-03h
   Adds: delivery_charge saved into request row + address_json + divisions
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$id     = (int)($_POST['id']     ?? 0);
$action = trim($_POST['action']  ?? '');
if ($id <= 0 || $action === '') {
    jsonResponse(false, 'Invalid request.');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM customer_address_requests WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$req) jsonResponse(false, 'Request not found.');
} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}

/* =========================================================
   UN-APPROVE
   ========================================================= */
if ($action === 'unapprove') {
    try {
        $pdo->beginTransaction();

        $cid = (int)($req['created_customer_id'] ?? 0);
        if ($cid > 0) {
            $ord = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = (
                SELECT customer_id FROM customers WHERE id = ? LIMIT 1
            )");
            $ord->execute([$cid]);
            $hasOrders = (int)$ord->fetchColumn() > 0;

            if (!$hasOrders) {
                $pdo->prepare("DELETE FROM customers WHERE id = ? LIMIT 1")
                    ->execute([$cid]);
            }
        }

        $pdo->prepare(
            "UPDATE customer_address_requests
             SET status = 0,
                 created_customer_id = NULL,
                 approved_at = NULL,
                 approved_by_id = NULL
             WHERE id = ?"
        )->execute([$id]);

        $pdo->commit();
        jsonResponse(true, 'Request set to pending. Apartment kept intact.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to unapprove: ' . $e->getMessage());
    }
}

/* =========================================================
   REJECT
   ========================================================= */
if ($action === 'reject') {
    try {
        $pdo->prepare("UPDATE customer_address_requests SET status = 2 WHERE id = ?")
            ->execute([$id]);
        jsonResponse(true, 'Request rejected.');
    } catch (PDOException $e) {
        jsonResponse(false, 'Failed to reject.');
    }
}

/* =========================================================
   APPROVE
   ========================================================= */
if ($action !== 'approve') {
    jsonResponse(false, 'Unknown action.');
}

/* ---------- Collect fields ---------- */
$name        = trim($_POST['customer_name']        ?? $req['customer_name']);
$mobile      = trim($_POST['customer_mobile']      ?? $req['customer_mobile']);
$password    = trim($_POST['password']             ?? '');
$reqType     = trim($_POST['request_type']         ?? $req['request_type']);
$aptName     = trim($_POST['requested_apartment']  ?? $req['requested_apartment']);
$aptAddress  = trim($_POST['apartment_address']    ?? '');
$branchId    = (int)($_POST['branch_id']           ?? 0);
$division    = trim($_POST['requested_division']   ?? ($req['requested_division'] ?? ''));
$mapLink     = trim($_POST['map_link']             ?? ($req['map_link'] ?? ''));
$houseImg    = trim($_POST['house_image']          ?? ($req['house_image'] ?? ''));
$deliveryCharge = (float)($_POST['delivery_charge'] ?? 0);
if ($deliveryCharge < 0) $deliveryCharge = 0;

/* ---------- Validate ---------- */
if ($name === '' || mb_strlen($name) < 3) jsonResponse(false, 'Name must be at least 3 characters.');
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) jsonResponse(false, 'Mobile number must be 10–15 digits.');
if (!in_array($reqType, ['apartment', 'individual_house'], true)) {
    jsonResponse(false, 'Invalid request type.');
}
if ($reqType === 'apartment' && $aptName === '') {
    jsonResponse(false, 'Apartment name is required.');
}
if ($reqType === 'individual_house' && $aptName === '') {
    jsonResponse(false, 'Full address is required.');
}

/* ---------- Password ---------- */
if ($password !== '') {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
} elseif (!empty($req['password_hash'])) {
    $passwordHash = $req['password_hash'];
} else {
    $passwordHash = password_hash('123456', PASSWORD_DEFAULT);
}

/* ---------- Existing customer? ---------- */
try {
    $chk = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? LIMIT 1");
    $chk->execute([$mobile]);
    $existingCustomer = $chk->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}

try {
    $pdo->beginTransaction();

    $apartmentIdCreated = null;
    $apartmentCodeOut   = null;

    if ($reqType === 'apartment') {

        /* ---------- Existing apartment? ---------- */
        $aStmt = $pdo->prepare("SELECT id, apartment_code, divisions FROM apartments WHERE LOWER(apartment_name) = LOWER(?) LIMIT 1");
        $aStmt->execute([$aptName]);
        $aptRow = $aStmt->fetch(PDO::FETCH_ASSOC);

        if ($aptRow) {
            $apartmentIdCreated = (int)$aptRow['id'];
            $apartmentCodeOut   = $aptRow['apartment_code'];

            if ($division !== '') {
                $existingDivs = json_decode($aptRow['divisions'] ?? '[]', true);
                if (!is_array($existingDivs)) $existingDivs = [];

                $matchedIndex = -1;
                foreach ($existingDivs as $i => $d) {
                    $parts = [];
                    if (!empty($d['block']))   $parts[] = "Blk " . $d['block'];
                    if (!empty($d['door_no'])) $parts[] = "Door " . $d['door_no'];
                    if (!empty($d['floor']))   $parts[] = "Fl " . $d['floor'];
                    $label = $parts ? implode(" / ", $parts) : (string)($d['division'] ?? '');
                    if (strcasecmp(trim($label), trim($division)) === 0) {
                        $matchedIndex = $i;
                        break;
                    }
                }

                if ($matchedIndex >= 0) {
                    $existingDivs[$matchedIndex]['charge'] = $deliveryCharge;
                } else {
                    $block = '';
                    $doorNo = '';
                    $floor = '';
                    if (preg_match('/Blk\s*([^\/]+)/i', $division, $m))  $block  = trim($m[1]);
                    if (preg_match('/Door\s*([^\/]+)/i', $division, $m)) $doorNo = trim($m[1]);
                    if (preg_match('/Fl\s*([^\/]+)/i', $division, $m))   $floor  = trim($m[1]);
                    if ($block === '' && $doorNo === '' && $floor === '') {
                        $doorNo = $division;
                    }
                    $existingDivs[] = [
                        'block'   => $block,
                        'door_no' => $doorNo,
                        'floor'   => $floor,
                        'charge'  => $deliveryCharge
                    ];
                }

                $pdo->prepare("UPDATE apartments SET divisions = ? WHERE id = ?")
                    ->execute([json_encode($existingDivs, JSON_UNESCAPED_UNICODE), $apartmentIdCreated]);
            }
        } else {
            /* Create new apartment */
            $apartmentCodeOut = 'APT' . strtoupper(bin2hex(random_bytes(4)));

            $initialDivs = [];
            if ($division !== '') {
                $block = '';
                $doorNo = '';
                $floor = '';
                if (preg_match('/Blk\s*([^\/]+)/i', $division, $m))  $block  = trim($m[1]);
                if (preg_match('/Door\s*([^\/]+)/i', $division, $m)) $doorNo = trim($m[1]);
                if (preg_match('/Fl\s*([^\/]+)/i', $division, $m))   $floor  = trim($m[1]);
                if ($block === '' && $doorNo === '' && $floor === '') {
                    $doorNo = $division;
                }
                $initialDivs[] = [
                    'block'   => $block,
                    'door_no' => $doorNo,
                    'floor'   => $floor,
                    'charge'  => $deliveryCharge
                ];
            }

            $insApt = $pdo->prepare(
                "INSERT INTO apartments
                    (apartment_code, apartment_name, apartment_address, branch_id, divisions, status)
                 VALUES (?, ?, ?, ?, ?, 1)"
            );
            $insApt->execute([
                $apartmentCodeOut,
                $aptName,
                $aptAddress,
                $branchId > 0 ? $branchId : null,
                json_encode($initialDivs, JSON_UNESCAPED_UNICODE)
            ]);
            $apartmentIdCreated = (int)$pdo->lastInsertId();
        }

        $addressJson = [
            'apartment_id'    => $apartmentIdCreated,
            'apartment_code'  => $apartmentCodeOut,
            'apartment_name'  => $aptName,
            'division_label'  => $division,
            'delivery_charge' => $deliveryCharge,
            'map_link'        => $mapLink,      /* ✅ ADD THIS */
            'house_image'     => $houseImg,     /* ✅ ADD THIS */
        ];
    } else {
        /* Individual house */
        $addressJson = [
            'full_address'    => $aptName,
            'map_link'        => $mapLink,
            'house_image'     => $houseImg,
            'delivery_charge' => $deliveryCharge,
        ];
    }
    $addressJsonFinal = json_encode($addressJson, JSON_UNESCAPED_UNICODE);

    /* ---------- Create or update customer ---------- */
    if ($existingCustomer) {
        $custId = (int)$existingCustomer['id'];
        $pdo->prepare(
            "UPDATE customers
             SET full_name = ?, password_hash = ?,
                 address_type = ?, address_json = ?,
                 profile_completed = 1, status = 1
             WHERE id = ?"
        )->execute([$name, $passwordHash, $reqType, $addressJsonFinal, $custId]);
    } else {
        $customerCode = 'MRSMC' . random_int(10000, 99999);
        $pdo->prepare(
            "INSERT INTO customers
                (customer_id, full_name, mobile_number,
                 password_hash, address_type, address_json,
                 profile_completed, status)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1)"
        )->execute([
            $customerCode,
            $name,
            $mobile,
            $passwordHash,
            $reqType,
            $addressJsonFinal
        ]);
        $custId = (int)$pdo->lastInsertId();
    }

    /* ---------- Mark request approved ---------- */
    $pdo->prepare(
        "UPDATE customer_address_requests
         SET status = 1,
             delivery_charge      = ?,
             created_customer_id  = ?,
             created_apartment_id = ?,
             created_branch_id    = ?,
             approved_at          = NOW(),
             approved_by_id       = ?
         WHERE id = ?"
    )->execute([
        $deliveryCharge,
        $custId,
        $apartmentIdCreated,
        $branchId > 0 ? $branchId : null,
        (int)$_SESSION['admin_id'],
        $id
    ]);

    $pdo->commit();

    jsonResponse(true, 'Request approved and customer created.', [
        'request_id'      => $id,
        'customer_id'     => $custId,
        'apartment_id'    => $apartmentIdCreated,
        'delivery_charge' => $deliveryCharge
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Failed to approve: ' . $e->getMessage());
}
