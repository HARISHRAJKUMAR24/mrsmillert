<?php
/* =========================================================
   MRS MILL@ — AJAX: GET SETTINGS
   File: ./ajax/settings-get.php
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

    jsonResponse(true, 'OK', $settings);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load settings.');
}