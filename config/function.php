<?php
/* =========================================================
   MRS MILL@ — SHARED FUNCTIONS
   File: ./config/function.php
   ========================================================= */

/* -----------------------------------------
   JSON RESPONSE HELPER
----------------------------------------- */

if (!function_exists('jsonResponse')) {

    function jsonResponse($success, $message, $data = null)
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => (bool) $success,
            'message' => $message,
            'data'    => $data
        ]);
        exit;
    }
}


/* -----------------------------------------
   AUTO-GENERATE APARTMENT CODE
   Example: APT001, APT002 ...
----------------------------------------- */

if (!function_exists('generateApartmentCode')) {

    function generateApartmentCode($pdo)
    {
        try {

            $stmt = $pdo->query(
                "SELECT apartment_code
                 FROM apartments
                 ORDER BY id DESC
                 LIMIT 1"
            );

            $last = $stmt->fetch();

            if (!$last) {
                return 'APT001';
            }

            $num = (int) substr($last['apartment_code'], 3);

            return 'APT' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
        } catch (PDOException $e) {

            return 'APT001';
        }
    }
}


/* -----------------------------------------
   FETCH ALL APARTMENTS
   (decodes divisions JSON automatically)
----------------------------------------- */

if (!function_exists('getAllApartments')) {

    function getAllApartments($pdo)
    {
        try {

            $stmt = $pdo->query(
                "SELECT * FROM apartments ORDER BY id DESC"
            );

            $rows = $stmt->fetchAll();

            foreach ($rows as &$row) {
                $row['divisions'] = decodeDivisions($row['divisions'] ?? '');
            }

            return $rows;
        } catch (PDOException $e) {

            return [];
        }
    }
}


/* -----------------------------------------
   FETCH ONE APARTMENT
----------------------------------------- */

if (!function_exists('getApartment')) {

    function getApartment($pdo, $id)
    {
        try {

            $stmt = $pdo->prepare(
                "SELECT * FROM apartments WHERE id = ? LIMIT 1"
            );

            $stmt->execute([$id]);

            $row = $stmt->fetch();

            if ($row) {
                $row['divisions'] = decodeDivisions($row['divisions'] ?? '');
            }

            return $row;
        } catch (PDOException $e) {

            return null;
        }
    }
}


/* -----------------------------------------
   DECODE DIVISIONS JSON
   Always returns an array
----------------------------------------- */

if (!function_exists('decodeDivisions')) {

    function decodeDivisions($json)
    {
        if (is_array($json)) {
            return $json;
        }

        $decoded = json_decode((string) $json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $clean = [];

        foreach ($decoded as $d) {

            if (!isset($d['division'])) continue;

            $clean[] = [
                'division' => (string) $d['division'],
                'charge'   => (float) ($d['charge'] ?? 0)
            ];
        }

        return $clean;
    }
}


/* -----------------------------------------
   SANITIZE OUTPUT
----------------------------------------- */

if (!function_exists('e')) {

    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}


/* -----------------------------------------
   REQUIRE ADMIN LOGIN
----------------------------------------- */

if (!function_exists('requireLogin')) {

    function requireLogin($pdo)
    {
        // If user is not logged in or token is invalid
        if (!isLoggedIn() || !verifyToken($pdo)) {

            // Prevent redirect loop if already on login page
            $currentPage = basename($_SERVER['PHP_SELF']);

            if ($currentPage !== 'login.php') {
                header('Location: login.php');
                exit;
            }
        }
    }
}

requireLogin($pdo);
