<?php
/* =========================================================
   MRS MILL@ — MENU APARTMENT ORDERS (single endpoint)
   File: ./ajax/menu-apartment-orders.php

   Actions:
     - menus                     → list all menus
     - apartments_with_orders    → apartments that have orders in a menu
     - summary                   → products + orders for menu + apartment

   NOTE: orders.products_json does NOT reliably contain "menu_code",
         so we scope orders by the menu's start_at/end_at window only.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$action = trim($_GET['action'] ?? '');

try {
    /* =========================================================
       ACTION: menus
       ========================================================= */
    if ($action === 'menus') {

        $stmt = $pdo->query(
            "SELECT id, menu_code, menu_name, start_at, end_at, status
             FROM menus
             ORDER BY id DESC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'        => (int)$r['id'],
                'menu_code' => $r['menu_code'],
                'menu_name' => $r['menu_name'],
                'start_at'  => $r['start_at'],
                'end_at'    => $r['end_at'],
                'status'    => (int)$r['status'],
            ];
        }
        jsonResponse(true, 'OK', $out);
    }

    /* =========================================================
       ACTION: apartments_with_orders
       ========================================================= */
    if ($action === 'apartments_with_orders') {

        $menuCode = trim($_GET['menu_code'] ?? '');
        if ($menuCode === '') jsonResponse(false, 'Menu required.');

        $mStmt = $pdo->prepare(
            "SELECT id, start_at, end_at FROM menus WHERE menu_code = ? LIMIT 1"
        );
        $mStmt->execute([$menuCode]);
        $menu = $mStmt->fetch(PDO::FETCH_ASSOC);
        if (!$menu) jsonResponse(false, 'Menu not found.');

        // Scope only by menu time window + apartment + not cancelled.
        $stmt = $pdo->prepare(
            "SELECT o.apartment_id,
                    o.apartment_code,
                    o.apartment_name,
                    COUNT(DISTINCT o.id) AS order_count
             FROM orders o
             WHERE o.apartment_id IS NOT NULL
               AND o.apartment_id > 0
               AND o.status <> 'cancelled'
               AND o.created_at BETWEEN ? AND ?
             GROUP BY o.apartment_id, o.apartment_code, o.apartment_name
             ORDER BY o.apartment_name ASC"
        );
        $stmt->execute([$menu['start_at'], $menu['end_at']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = array_map(function ($r) {
            return [
                'id'             => (int)$r['apartment_id'],
                'apartment_code' => $r['apartment_code'] ?? '',
                'apartment_name' => $r['apartment_name'] ?? '',
                'order_count'    => (int)$r['order_count'],
            ];
        }, $rows);

        jsonResponse(true, 'OK', $out);
    }

    /* =========================================================
       ACTION: summary
       ========================================================= */
    if ($action === 'summary') {

        $apartmentId = (int)($_GET['apartment_id'] ?? 0);
        $menuCode    = trim($_GET['menu_code'] ?? '');
        $date        = trim($_GET['date'] ?? 'all');
        $from        = trim($_GET['from'] ?? '');
        $to          = trim($_GET['to']   ?? '');

        if ($apartmentId <= 0) jsonResponse(false, 'Invalid apartment.');
        if ($menuCode === '')  jsonResponse(false, 'Menu required.');

        /* Apartment */
        $aStmt = $pdo->prepare(
            "SELECT id, apartment_code, apartment_name
             FROM apartments WHERE id = ? LIMIT 1"
        );
        $aStmt->execute([$apartmentId]);
        $apt = $aStmt->fetch(PDO::FETCH_ASSOC);
        if (!$apt) jsonResponse(false, 'Apartment not found.');

        /* Menu */
        $mStmt = $pdo->prepare(
            "SELECT id, menu_code, menu_name, start_at, end_at
             FROM menus WHERE menu_code = ? LIMIT 1"
        );
        $mStmt->execute([$menuCode]);
        $menu = $mStmt->fetch(PDO::FETCH_ASSOC);
        if (!$menu) jsonResponse(false, 'Menu not found.');

        /* Build WHERE — scope by menu window, apartment, not cancelled */
        $where = [
            "o.apartment_id = ?",
            "o.status <> 'cancelled'",
            "o.created_at BETWEEN ? AND ?"
        ];
        $params = [$apartmentId, $menu['start_at'], $menu['end_at']];

        if ($date === 'today') {
            $where[] = "DATE(o.created_at) = CURDATE()";
        } elseif ($date === 'yesterday') {
            $where[] = "DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        } elseif ($date === 'week') {
            $where[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
        } elseif ($date === 'month') {
            $where[] = "YEAR(o.created_at) = YEAR(CURDATE())
                        AND MONTH(o.created_at) = MONTH(CURDATE())";
        } elseif ($date === 'custom') {
            if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $where[]  = "DATE(o.created_at) >= ?";
                $params[] = $from;
            }
            if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $where[]  = "DATE(o.created_at) <= ?";
                $params[] = $to;
            }
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT o.id, o.order_code, o.customer_name, o.customer_mobile,
                       o.division, o.subtotal, o.total_amount,
                       o.products_json, o.status, o.payment_status, o.created_at
                FROM orders o
                $whereSql
                ORDER BY o.created_at DESC, o.id DESC
                LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /* Aggregate */
        $productAgg  = [];
        $orders      = [];
        $totalQty    = 0;
        $totalAmount = 0.0;

        foreach ($rows as $r) {
            $items = json_decode($r['products_json'] ?? '[]', true);
            if (!is_array($items)) $items = [];

            $itemSummaryParts = [];

            foreach ($items as $it) {
                $code    = $it['code'] ?? '';
                $name    = $it['name'] ?? '';
                $image   = $it['image'] ?? '';
                $varId   = (int)($it['variant_id'] ?? 0);
                $varName = $it['variant_name'] ?? '';
                $varQty  = $it['variant_qty'] ?? '';
                $qty     = (int)($it['qty'] ?? 0);
                if ($qty <= 0) continue;

                $totalQty += $qty;
                $key = $code . '_' . $varId;

                if (!isset($productAgg[$key])) {
                    $productAgg[$key] = [
                        'code'     => $code,
                        'name'     => $name,
                        'image'    => $image,
                        'qty'      => 0,
                        'variants' => [],
                    ];
                }

                $productAgg[$key]['qty'] += $qty;

                $varKey = $varName ?: ($varQty ?: 'Default');
                if (!isset($productAgg[$key]['variants'][$varKey])) {
                    $productAgg[$key]['variants'][$varKey] = 0;
                }
                $productAgg[$key]['variants'][$varKey] += $qty;

                $itemSummaryParts[] = $name . ' ×' . $qty;
            }

            $totalAmount += (float)$r['total_amount'];

            $orders[] = [
                'id'              => (int)$r['id'],
                'order_code'      => $r['order_code'],
                'customer_name'   => $r['customer_name'],
                'customer_mobile' => $r['customer_mobile'],
                'division'        => $r['division'],
                'status'          => $r['status'],
                'payment_status'  => $r['payment_status'],
                'total_amount'    => (float)$r['total_amount'],
                'created_at'      => $r['created_at'],
                'items_summary'   => implode(', ', array_slice($itemSummaryParts, 0, 2))
                                     . (count($itemSummaryParts) > 2
                                        ? ' +' . (count($itemSummaryParts) - 2) . ' more'
                                        : ''),
            ];
        }

        /* Format product variants */
        $products = [];
        foreach ($productAgg as $p) {
            $variants = [];
            foreach ($p['variants'] as $name => $qty) {
                $variants[] = ['name' => $name, 'qty' => $qty];
            }
            $products[] = [
                'code'     => $p['code'],
                'name'     => $p['name'],
                'image'    => $p['image'],
                'qty'      => $p['qty'],
                'variants' => $variants,
            ];
        }

        usort($products, fn($a, $b) => $b['qty'] - $a['qty']);

        jsonResponse(true, 'OK', [
            'apartment_id'   => (int)$apt['id'],
            'apartment_code' => $apt['apartment_code'],
            'apartment_name' => $apt['apartment_name'],
            'menu_code'      => $menu['menu_code'],
            'menu_name'      => $menu['menu_name'],
            'kpis' => [
                'order_count'   => count($orders),
                'product_count' => count($products),
                'total_qty'     => $totalQty,
                'total_amount'  => round($totalAmount, 2),
            ],
            'products' => $products,
            'orders'   => $orders,
        ]);
    }

    jsonResponse(false, 'Invalid action.');

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}