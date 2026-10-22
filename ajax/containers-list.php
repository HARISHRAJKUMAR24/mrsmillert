<?php
/* =========================================================
   MRS MILL@ — AJAX: LIST CONTAINERS (admin)
   File: ./ajax/containers-list.php

   Fix:
   - customer_name / customer_mobile live in `customers`,
     NOT on `orders`. Join customers via customer_id.
   - Fallback: if a container row has no customer_id, use
     o.customer_id to resolve the customer.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

try {
    /* -------- Ensure schema pieces exist (idempotent) -------- */
    $alters = [
        "ALTER TABLE order_containers ADD COLUMN received_containers INT(11) NOT NULL DEFAULT 0 AFTER total_containers",
        "ALTER TABLE order_containers ADD COLUMN status ENUM('not_received','partial','received') NOT NULL DEFAULT 'not_received' AFTER container_amount",
        "ALTER TABLE order_containers ADD COLUMN received_at DATETIME DEFAULT NULL AFTER status",
        "ALTER TABLE order_containers ADD COLUMN received_by_id INT(11) DEFAULT NULL AFTER received_at",
        "ALTER TABLE order_containers ADD COLUMN received_by_name VARCHAR(150) DEFAULT NULL AFTER received_by_id",
        "ALTER TABLE order_containers ADD COLUMN note VARCHAR(255) DEFAULT NULL AFTER received_by_name",
    ];
    foreach ($alters as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* already exists */ }
    }

    /* -------- Main query: join customers via customer_id -------- */
    $sql = "
        SELECT
            oc.id,
            oc.order_id,
            oc.customer_id,

            oc.total_containers,
            oc.received_containers,
            (oc.total_containers - oc.received_containers) AS pending_containers,
            oc.container_amount,
            CASE
                WHEN oc.total_containers > 0
                THEN ROUND(oc.container_amount / oc.total_containers, 2)
                ELSE 0
            END AS container_unit_amount,

            oc.status,
            oc.received_at,
            oc.received_by_name,
            oc.note,
            oc.created_at,

            o.order_code,
            o.customer_id AS order_customer_ref,

            /* Resolve customer via EITHER oc.customer_id or o.customer_id */
            COALESCE(c_oc.full_name,     c_o.full_name)     AS customer_name,
            COALESCE(c_oc.mobile_number, c_o.mobile_number) AS customer_mobile,
            COALESCE(oc.customer_id,     c_o.id)            AS resolved_customer_id

        FROM order_containers oc
        LEFT JOIN orders o        ON o.id           = oc.order_id
        LEFT JOIN customers c_oc  ON c_oc.id        = oc.customer_id
        LEFT JOIN customers c_o   ON c_o.customer_id = o.customer_id
        ORDER BY oc.id DESC
        LIMIT 1000
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'                    => (int)$r['id'],
            'order_id'              => (int)$r['order_id'],
            'order_code'            => $r['order_code'] ?? '',
            'customer_id'           => $r['resolved_customer_id'] ? (int)$r['resolved_customer_id'] : null,
            'customer_name'         => $r['customer_name']   ?: '—',
            'customer_mobile'       => $r['customer_mobile'] ?: '',
            'total_containers'      => (int)$r['total_containers'],
            'received_containers'   => (int)$r['received_containers'],
            'pending_containers'    => (int)$r['pending_containers'],
            'container_amount'      => (float)$r['container_amount'],
            'container_unit_amount' => (float)$r['container_unit_amount'],
            'status'                => $r['status'] ?? 'not_received',
            'received_at'           => $r['received_at'],
            'received_by_name'      => $r['received_by_name'],
            'note'                  => $r['note'],
            'created_at'            => $r['created_at'],
        ];
    }

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    /* Return the real error so debugging is possible */
    jsonResponse(false, 'Failed to load containers: ' . $e->getMessage());
}