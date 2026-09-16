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
                "SELECT MAX(CAST(SUBSTRING(apartment_code, 4) AS UNSIGNED)) AS max_num
                 FROM apartments
                 WHERE apartment_code LIKE 'APT%'"
            );

            $row  = $stmt->fetch();
            $next = 1;

            if ($row && $row['max_num'] !== null) {
                $next = ((int) $row['max_num']) + 1;
            }

            return 'APT' . str_pad($next, 3, '0', STR_PAD_LEFT);
        } catch (PDOException $e) {

            return 'APT001';
        }
    }
}


/* -----------------------------------------
   AUTO-GENERATE PRODUCT CODE
   Example: PRD001, PRD002 ...
----------------------------------------- */

if (!function_exists('generateProductCode')) {

    function generateProductCode($pdo)
    {
        try {

            $stmt = $pdo->query(
                "SELECT MAX(CAST(SUBSTRING(product_code, 4) AS UNSIGNED)) AS max_num
                 FROM products
                 WHERE product_code LIKE 'PRD%'"
            );

            $row  = $stmt->fetch();
            $next = 1;

            if ($row && $row['max_num'] !== null) {
                $next = ((int) $row['max_num']) + 1;
            }

            return 'PRD' . str_pad($next, 3, '0', STR_PAD_LEFT);
        } catch (PDOException $e) {

            return 'PRD001';
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
        if (!isLoggedIn() || !verifyToken($pdo)) {

            $currentPage = basename($_SERVER['PHP_SELF']);

            if ($currentPage !== 'login.php') {
                header('Location: login.php');
                exit;
            }
        }
    }
}

requireLogin($pdo);


/* =========================================================
   CATEGORY HELPERS
   ========================================================= */

if (!function_exists('makeSlug')) {
    function makeSlug($text)
    {
        $text = strtolower(trim((string) $text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        return $text === '' ? 'category' : $text;
    }
}


if (!function_exists('uploadCategoryImage')) {
    function uploadCategoryImage($file, $baseDir)
    {
        if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'message' => 'No image uploaded.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload error code: ' . $file['error']];
        }

        if ($file['size'] > 3 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Image must be under 3 MB.'];
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif'
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowed[$mime])) {
            return ['success' => false, 'message' => 'Only JPG, PNG, WEBP or GIF allowed.'];
        }

        $ext = $allowed[$mime];

        $datePart   = date('Ymd');
        $randomPart = bin2hex(random_bytes(5));

        $relFolder = 'uploads/category/' . $datePart . '/' . $randomPart . '/';
        $absFolder = rtrim($baseDir, '/\\') . '/' . $relFolder;

        if (!is_dir($absFolder)) {
            if (!mkdir($absFolder, 0775, true)) {
                return ['success' => false, 'message' => 'Could not create upload folder.'];
            }
        }

        $fileName = 'image.' . $ext;
        $absPath  = $absFolder . $fileName;
        $relPath  = $relFolder . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            return ['success' => false, 'message' => 'Could not save uploaded file.'];
        }

        return [
            'success' => true,
            'message' => 'Uploaded.',
            'path'    => $relPath,
            'abs'     => $absPath
        ];
    }
}


if (!function_exists('deleteCategoryImage')) {
    function deleteCategoryImage($relPath, $baseDir)
    {
        $relPath = trim((string) $relPath);
        if ($relPath === '') return;

        $absPath = rtrim($baseDir, '/\\') . '/' . ltrim($relPath, '/\\');

        if (is_file($absPath)) {
            @unlink($absPath);
        }

        $folder = dirname($absPath);
        if (is_dir($folder)) {
            $items = @scandir($folder);
            if ($items !== false && count($items) <= 2) {
                @rmdir($folder);
            }
        }
    }
}


if (!function_exists('getCategory')) {
    function getCategory($pdo, $id)
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM categories WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
}


/* =========================================================
   SETTINGS HELPERS
   ========================================================= */

/* -----------------------------------------
   UPLOAD SETTINGS IMAGE
   Path: uploads/settings/YYYYMMDD/<random>/file.ext
   $type: 'favicon' or 'logo'
----------------------------------------- */

if (!function_exists('uploadSettingsImage')) {
    function uploadSettingsImage($file, $baseDir, $type = 'logo')
    {
        if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'message' => 'No image uploaded.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload error code: ' . $file['error']];
        }

        if ($file['size'] > 3 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Image must be under 3 MB.'];
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
            'image/svg+xml' => 'svg'
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // allow ico extension fallback
        if (!isset($allowed[$mime]) && preg_match('/\.(ico|svg)$/i', $file['name'])) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext === 'ico' || $ext === 'svg') {
                $mime = $ext === 'ico' ? 'image/x-icon' : 'image/svg+xml';
            }
        }

        if (!isset($allowed[$mime])) {
            return ['success' => false, 'message' => 'Only JPG, PNG, WEBP, GIF, SVG or ICO allowed.'];
        }

        $ext = $allowed[$mime];

        $datePart   = date('Ymd');
        $randomPart = bin2hex(random_bytes(5));

        $relFolder = 'uploads/settings/' . $datePart . '/' . $randomPart . '/';
        $absFolder = rtrim($baseDir, '/\\') . '/' . $relFolder;

        if (!is_dir($absFolder)) {
            if (!mkdir($absFolder, 0775, true)) {
                return ['success' => false, 'message' => 'Could not create upload folder.'];
            }
        }

        $fileName = $type . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        $absPath  = $absFolder . $fileName;
        $relPath  = $relFolder . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            return ['success' => false, 'message' => 'Could not save uploaded file.'];
        }

        return [
            'success' => true,
            'message' => 'Uploaded.',
            'path'    => $relPath,
            'abs'     => $absPath
        ];
    }
}


/* -----------------------------------------
   DELETE FILE + EMPTY FOLDER
----------------------------------------- */

if (!function_exists('deleteStoredFile')) {
    function deleteStoredFile($relPath, $baseDir)
    {
        $relPath = trim((string) $relPath);
        if ($relPath === '') return;

        $absPath = rtrim($baseDir, '/\\') . '/' . ltrim($relPath, '/\\');

        if (is_file($absPath)) {
            @unlink($absPath);
        }

        $folder = dirname($absPath);
        if (is_dir($folder)) {
            $items = @scandir($folder);
            if ($items !== false && count($items) <= 2) {
                @rmdir($folder);
            }
        }
    }
}


/* -----------------------------------------
   GET SETTINGS ROW (single row, id = 1)
----------------------------------------- */

if (!function_exists('getSettings')) {
    function getSettings($pdo)
    {
        try {
            $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
            $row = $stmt->fetch();

            if (!$row) {
                // insert default
                $pdo->exec("INSERT INTO settings (id) VALUES (1)");
                $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
                $row = $stmt->fetch();
            }

            return $row;
        } catch (PDOException $e) {
            return null;
        }
    }
}


if (!function_exists('generateMenuCode')) {
    function generateMenuCode(PDO $pdo): string
    {
        try {
            $stmt = $pdo->query(
                "SELECT menu_code FROM menus ORDER BY id DESC LIMIT 1"
            );
            $last = $stmt->fetchColumn();
            if (!$last) return 'MEN001';

            $num = (int) preg_replace('/[^0-9]/', '', $last);
            $num++;

            return 'MEN' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
        } catch (PDOException $e) {
            return 'MEN001';
        }
    }
}