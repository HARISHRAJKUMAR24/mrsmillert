<?php
/* =========================================================
   MRS MILL@ — AJAX: GET SETTINGS
   File: ./ajax/settings-get.php
   Returns settings + list of branches
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.');
}

try {

    $settings = getSettings($pdo);

    if (!$settings) {
        jsonResponse(false, 'Settings not found.');
    }

    $settings['favicon_url'] = $settings['favicon_image']
        ? ADMIN_URL . $settings['favicon_image']
        : '';

    $settings['logo_url'] = $settings['logo_image']
        ? ADMIN_URL . $settings['logo_image']
        : '';

    /* Load branches */
    $branches = [];
    try {
        $stmt = $pdo->query(
            "SELECT id, branch_name, branch_address, branch_mobile, branch_email
             FROM settings_branches
             ORDER BY id ASC"
        );
        $branches = $stmt->fetchAll();
    } catch (PDOException $e) {
        $branches = [];
    }

    $settings['branches'] = $branches;

    jsonResponse(true, 'OK', $settings);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load settings.');
}