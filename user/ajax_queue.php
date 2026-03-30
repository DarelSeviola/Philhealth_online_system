<?php

declare(strict_types=1);

session_name('user_session');
session_start();

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/helpers.php";

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'user')) {
    echo json_encode([
        "ok" => false,
        "error" => "NOT_LOGGED_IN",
        "message" => "Kailangan munang mag-login upang makita ang inyong queue number."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$today   = date('Y-m-d');

// Uses YOUR schema: appointments + queue + service_counters
$stmt = $conn->prepare("
    SELECT
        q.queue_id,
        q.queue_code,
        q.status,
        q.counter_id,
        sc.counter_name,
        q.category_id,
        qc.category_name,
        q.queue_date,
        q.queued_at
    FROM appointments a
    JOIN queue q ON q.appointment_id = a.appointment_id
    LEFT JOIN service_counters sc ON sc.counter_id = q.counter_id
    LEFT JOIN queue_categories qc ON qc.category_id = q.category_id
    WHERE a.user_id = ?
      AND q.queue_date = ?
    ORDER BY q.queue_id DESC
    LIMIT 1
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode([
        "ok" => true,
        "has_queue" => false,
        "message" => "Wala pa kayong queue ngayon. Kung may booking kayo, mag-check in sa kiosk para makakuha ng queue number."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    "ok" => true,
    "has_queue" => true,
    "queue" => [
        "queue_id"      => (int)$row['queue_id'],
        "queue_code"    => (string)$row['queue_code'],
        "status"        => (string)$row['status'],
        "counter_id"    => $row['counter_id'] !== null ? (int)$row['counter_id'] : null,
        "counter_name"  => $row['counter_name'] !== null ? (string)$row['counter_name'] : null,
        "category_id"   => $row['category_id'] !== null ? (int)$row['category_id'] : null,
        "category_name" => $row['category_name'] !== null ? (string)$row['category_name'] : null,
        "queue_date"    => (string)$row['queue_date'],
        "queued_at"     => $row['queued_at'] !== null ? (string)$row['queued_at'] : null
    ]
], JSON_UNESCAPED_UNICODE);
