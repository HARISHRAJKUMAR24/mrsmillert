<?php
/* =========================================================
   MRS MILL@ — AJAX: APARTMENTS FOR A BOY
   File: ./ajax/get-apartments-for-boy.php

   Returns four buckets:
     available      → real apartments not allocated to any boy
     allocated      → real apartments allocated to THIS boy
     ih_available   → Individual Houses (from customers.address_json)
                      not allocated to any boy
     ih_allocated   → Individual Houses allocated to THIS boy
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$boyId = (int)($_GET['boy_id'] ?? 0);
if ($boyId <= 0) {
    jsonResponse(false, 'Invalid delivery boy.');
}

try {
    /* ---------- Verify boy ---------- */
    $bCheck = $pdo->prepare(
        "SELECT id FROM delivery_boys WHERE id = ? AND status = 1 LIMIT 1"
    );
    $bCheck->execute([$boyId]);
    if (!$bCheck->fetch()) {
        jsonResponse(false, 'Delivery boy not found.');
    }

    /* =========================================================
       REAL APARTMENTS
       ========================================================= */
    $availStmt = $pdo->prepare(
        "SELECT a.apartment_code, a.apartment_name, a.apartment_address
         FROM apartments a
         WHERE a.status = 1
           AND NOT EXISTS (
               SELECT 1 FROM apartment_delivery_boys adb
               WHERE adb.apartment_code = a.apartment_code
           )
         ORDER BY a.apartment_name ASC"
    );
    $availStmt->execute();
    $available = $availStmt->fetchAll(PDO::FETCH_ASSOC);

    $allocStmt = $pdo->prepare(
        "SELECT a.apartment_code, a.apartment_name, a.apartment_address,
                adb.allocated_at, adb.allocated_by
         FROM apartment_delivery_boys adb
         INNER JOIN apartments a ON a.apartment_code = adb.apartment_code
         WHERE adb.delivery_boy_id = ?
           AND a.status = 1
         ORDER BY a.apartment_name ASC"
    );
    $allocStmt->execute([$boyId]);
    $allocated = $allocStmt->fetchAll(PDO::FETCH_ASSOC);

    /* =========================================================
       INDIVIDUAL HOUSES
       Source: customers.address_json where address_type = 'individual_house'
       One entry per distinct full_address.
       Code = IH_<12 hex chars from md5(address)>
       ========================================================= */

    /* All distinct IH addresses from customers */
    $ihStmt = $pdo->query(
        "SELECT DISTINCT
                TRIM(JSON_UNQUOTE(JSON_EXTRACT(c.address_json, '$.full_address'))) AS full_address
         FROM customers c
         WHERE c.address_type = 'individual_house'
           AND c.status = 1
           AND JSON_VALID(c.address_json)
           AND JSON_UNQUOTE(JSON_EXTRACT(c.address_json, '$.full_address')) IS NOT NULL
           AND TRIM(JSON_UNQUOTE(JSON_EXTRACT(c.address_json, '$.full_address'))) <> ''
         ORDER BY full_address ASC"
    );
    $ihAddresses = $ihStmt->fetchAll(PDO::FETCH_COLUMN);

    /* Codes allocated to THIS boy */
    $ihAllocStmt = $pdo->prepare(
        "SELECT apartment_code, allocated_at, allocated_by
         FROM apartment_delivery_boys
         WHERE delivery_boy_id = ?
           AND apartment_code LIKE 'IH\\_%'"
    );
    $ihAllocStmt->execute([$boyId]);
    $ihAllocatedCodes = [];
    while ($row = $ihAllocStmt->fetch(PDO::FETCH_ASSOC)) {
        $ihAllocatedCodes[$row['apartment_code']] = $row;
    }

    /* Codes allocated to ANY boy (to skip for this boy's "available") */
    $ihAnyStmt = $pdo->query(
        "SELECT apartment_code, delivery_boy_id
         FROM apartment_delivery_boys
         WHERE apartment_code LIKE 'IH\\_%'"
    );
    $ihTakenCodes = [];    // code => true
    while ($row = $ihAnyStmt->fetch(PDO::FETCH_ASSOC)) {
        $ihTakenCodes[$row['apartment_code']] = (int)$row['delivery_boy_id'];
    }

    $ihAvailable = [];
    $ihAllocated = [];

    foreach ($ihAddresses as $addr) {
        $addr = trim((string)$addr);
        if ($addr === '') continue;

        $code = 'IH_' . substr(md5($addr), 0, 12);

        $entry = [
            'apartment_code'    => $code,
            'apartment_name'    => $addr,
            'apartment_address' => $addr,
            'is_individual'     => true,
        ];

        /* Allocated to THIS boy */
        if (isset($ihAllocatedCodes[$code])) {
            $entry['allocated_at'] = $ihAllocatedCodes[$code]['allocated_at'];
            $entry['allocated_by'] = $ihAllocatedCodes[$code]['allocated_by'];
            $ihAllocated[] = $entry;
            continue;
        }

        /* Allocated to someone ELSE → skip */
        if (isset($ihTakenCodes[$code])) {
            continue;
        }

        /* Free → available */
        $ihAvailable[] = $entry;
    }

    jsonResponse(true, 'OK', [
        'available'    => $available,
        'allocated'    => $allocated,
        'ih_available' => $ihAvailable,
        'ih_allocated' => $ihAllocated,
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load apartments: ' . $e->getMessage());
}