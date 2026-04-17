<?php
require_once __DIR__ . "/../config/db.php";

header('Content-Type: application/json; charset=utf-8');

// IMPORTANT: use the same timezone as the rest of your system
date_default_timezone_set('Asia/Manila');

$serviceId  = (int)($_GET['service_id'] ?? 0);
$clientType = trim((string)($_GET['client_status'] ?? 'regular'));
$date       = trim((string)($_GET['date'] ?? ''));

if (
    $serviceId <= 0 ||
    $date === '' ||
    !in_array($clientType, ['regular', 'senior', 'pregnant', 'pwd'], true)
) {
    echo json_encode([]);
    exit;
}

// Validate date format
$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT category_id
    FROM services
    WHERE service_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $serviceId);
$stmt->execute();
$svc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$svc) {
    echo json_encode([]);
    exit;
}

$categoryId = (int)$svc['category_id'];

$stmt = $conn->prepare("
    SELECT
        l.slot_time,
        l.max_capacity,
        COUNT(a.appointment_id) AS booked_count
    FROM appointment_slot_limits l
    LEFT JOIN appointments a
      ON a.category_id = l.category_id
      AND a.appointment_date = ?
      AND a.appointment_time = l.slot_time
      AND a.status IN ('booked', 'checked_in')
      AND (
        CASE
          WHEN a.client_status = 'Senior Citizen' THEN 'senior'
          WHEN a.client_status = 'Pregnant' THEN 'pregnant'
          WHEN a.client_status = 'PWD' THEN 'pwd'
          ELSE 'regular'
        END
      ) = l.client_type
    WHERE l.category_id = ?
      AND l.client_type = ?
    GROUP BY l.slot_id, l.slot_time, l.max_capacity
    HAVING booked_count < l.max_capacity
    ORDER BY l.slot_time ASC
");
$stmt->bind_param("sis", $date, $categoryId, $clientType);
$stmt->execute();
$res = $stmt->get_result();

$slots = [];
$today = date('Y-m-d');
$now   = time();

while ($row = $res->fetch_assoc()) {
    $slotTime = $row['slot_time']; // e.g. 08:00:00

    // For same-day booking, hide only slots that have already ENDED
    if ($date === $today) {
        $slotStartTs = strtotime($date . ' ' . $slotTime);
        $slotEndTs   = strtotime('+1 hour', $slotStartTs);

        if ($slotEndTs <= $now) {
            continue;
        }
    }

    $start = new DateTime($slotTime);
    $end   = (clone $start)->modify('+1 hour');

    $slots[] = [
        'slot_time' => $slotTime,
        'label'     => $start->format('h:i') . '–' . $end->format('h:i A'),
    ];
}

$stmt->close();

echo json_encode($slots, JSON_UNESCAPED_UNICODE);
