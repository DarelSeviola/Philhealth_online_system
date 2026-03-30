<?php
// config/helpers.php

/* =========================================================
   SAFE TEXT OUTPUT
   Used to safely show text in HTML
========================================================= */
function e(?string $str): string
{
  return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   REFERENCE CODE
   Creates a unique reference code for appointments
========================================================= */
function generate_reference_code($appointment_date)
{
  return 'REF-' . date('Ymd', strtotime($appointment_date)) . '-' .
    strtoupper(substr(md5(uniqid()), 0, 4));
}

/* =========================================================
   QUEUE CODE FORMAT
   Example:
   M2001
   B2001
========================================================= */
function format_queue_code(string $prefix, int $number): string
{
  $prefix = strtoupper(trim($prefix));
  return $prefix . (string)$number;
}

/* =========================================================
   GET TODAY'S DATE
========================================================= */
function today_date(): string
{
  return date('Y-m-d');
}

/* =========================================================
   NORMALIZE CLIENT STATUS
   Only allow valid client status values
========================================================= */
function normalize_client_status(string $s): string
{
  $s = trim($s);

  $allowed = [
    'Regular',
    'Senior Citizen',
    'PWD',
    'Pregnant',
    'Special Lane'
  ];

  return in_array($s, $allowed, true) ? $s : 'Regular';
}

/* =========================================================
   GET CATEGORY PREFIX
   Gets the queue prefix from queue_categories table
   Example:
   Membership = M
   Benefit Availment = B
========================================================= */
function get_category_prefix(mysqli $conn, int $category_id): string
{
  $stmt = $conn->prepare("
    SELECT prefix
    FROM queue_categories
    WHERE category_id = ?
      AND is_active = 1
    LIMIT 1
  ");

  if (!$stmt) {
    throw new Exception("DB error (prefix): " . $conn->error);
  }

  $stmt->bind_param("i", $category_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    throw new Exception("Invalid category.");
  }

  $prefix = strtoupper(trim((string)$row['prefix']));

  if ($prefix === '') {
    throw new Exception("Category prefix not configured.");
  }

  return $prefix;
}

/* =========================================================
   GET SERVICE INFO
   Gets service_id and category_id from services table
========================================================= */
function get_service_row(mysqli $conn, int $service_id): array
{
  $stmt = $conn->prepare("
    SELECT service_id, category_id, is_active
    FROM services
    WHERE service_id = ?
    LIMIT 1
  ");

  if (!$stmt) {
    throw new Exception("DB error (service): " . $conn->error);
  }

  $stmt->bind_param("i", $service_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    throw new Exception("Selected service not found.");
  }

  if ((int)$row['is_active'] !== 1) {
    throw new Exception("Selected service is not active.");
  }

  return [
    'service_id'  => (int)$row['service_id'],
    'category_id' => (int)$row['category_id'],
  ];
}

/* =========================================================
   COUNTER RULES
   - Special lane clients go directly to Counter 2
   - Regular clients do not get a fixed counter yet
========================================================= */
function compute_counter_id(mysqli $conn, int $category_id, string $client_status): ?int
{
  $client_status = normalize_client_status($client_status);

  if (in_array($client_status, ['Senior Citizen', 'PWD', 'Pregnant', 'Special Lane'], true)) {
    return 2;
  }

  // Regular clients will get counter later when staff clicks Call Next
  if ($category_id === 1 || $category_id === 2) {
    return null;
  }

  return null;
}

/* =========================================================
   COUNTER RULES USING SERVICE
   Keeps compatibility if other pages pass service_id
========================================================= */
function compute_counter_id_from_service(mysqli $conn, int $service_id, int $category_id, string $client_status): ?int
{
  $svc = get_service_row($conn, $service_id);
  $cat = $category_id > 0 ? $category_id : (int)$svc['category_id'];

  return compute_counter_id($conn, $cat, $client_status);
}

/* =========================================================
   ALLOCATE NEXT QUEUE NUMBER
   This is part of FCFS logic.
   It gets the highest queue number for the day and category,
   then adds 1.
========================================================= */
function allocate_next_queue_number(mysqli $conn, string $queue_date, int $category_id): int
{
  $lockKey = "qnum:{$queue_date}:cat{$category_id}";

  // Lock queue generation so two users do not get same number
  $stmt = $conn->prepare("SELECT GET_LOCK(?, 10) AS got");
  if (!$stmt) {
    throw new Exception("DB error (GET_LOCK): " . $conn->error);
  }

  $stmt->bind_param("s", $lockKey);
  $stmt->execute();
  $got = (int)($stmt->get_result()->fetch_assoc()['got'] ?? 0);
  $stmt->close();

  if ($got !== 1) {
    throw new Exception("Queue is busy. Please try again.");
  }

  try {
    // Get the last queue number for the same date and category
    $stmt = $conn->prepare("
      SELECT COALESCE(MAX(queue_number), 2000) AS mx
      FROM queue
      WHERE queue_date = ?
        AND category_id = ?
      FOR UPDATE
    ");

    if (!$stmt) {
      throw new Exception("DB error (max queue_number): " . $conn->error);
    }

    $stmt->bind_param("si", $queue_date, $category_id);
    $stmt->execute();
    $mx = (int)($stmt->get_result()->fetch_assoc()['mx'] ?? 2000);
    $stmt->close();

    // Next queue number
    return $mx + 1;
  } finally {
    $rel = $conn->prepare("SELECT RELEASE_LOCK(?)");
    if ($rel) {
      $rel->bind_param("s", $lockKey);
      $rel->execute();
      $rel->close();
    }
  }
}

/* =========================================================
   ISSUE QUEUE AT BOOKING OR CHECK-IN
   This creates the queue record for an appointment
========================================================= */
function issue_queue_at_booking(mysqli $conn, int $appointment_id): array
{
  if ($appointment_id <= 0) {
    throw new Exception("Invalid appointment_id.");
  }

  // Lock the appointment row first
  $stmt = $conn->prepare("
    SELECT appointment_id, service_id, category_id, counter_id, client_status, appointment_date
    FROM appointments
    WHERE appointment_id = ?
    LIMIT 1
    FOR UPDATE
  ");

  if (!$stmt) {
    throw new Exception("DB error (appointments): " . $conn->error);
  }

  $stmt->bind_param("i", $appointment_id);
  $stmt->execute();
  $apt = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$apt) {
    throw new Exception("Appointment not found.");
  }

  $service_id    = (int)$apt['service_id'];
  $category_id   = (int)$apt['category_id'];
  $client_status = normalize_client_status((string)($apt['client_status'] ?? 'Regular'));
  $queue_date    = (string)$apt['appointment_date'];

  if ($service_id <= 0) {
    throw new Exception("Service missing on appointment.");
  }

  if ($queue_date === '') {
    throw new Exception("Appointment date missing.");
  }

  // Validate service and fix missing category if needed
  $svc = get_service_row($conn, $service_id);
  if ($category_id <= 0) {
    $category_id = (int)$svc['category_id'];
  }

  // Special lane gets Counter 2, regular stays null for now
  $counter_id = compute_counter_id_from_service($conn, $service_id, $category_id, $client_status);

  // Save counter into appointment
  $up = $conn->prepare("
    UPDATE appointments
    SET counter_id = ?
    WHERE appointment_id = ?
  ");

  if (!$up) {
    throw new Exception("DB error (update counter): " . $conn->error);
  }

  $up->bind_param("ii", $counter_id, $appointment_id);
  $up->execute();
  $up->close();

  // If queue already exists, return existing queue
  $stmt = $conn->prepare("
    SELECT queue_id, queue_date, service_id, category_id, counter_id, prefix, queue_number, queue_code, status, created_at
    FROM queue
    WHERE appointment_id = ?
    LIMIT 1
    FOR UPDATE
  ");

  if (!$stmt) {
    throw new Exception("DB error (queue existing): " . $conn->error);
  }

  $stmt->bind_param("i", $appointment_id);
  $stmt->execute();
  $existing = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($existing) {
    return [
      'queue_id'       => (int)$existing['queue_id'],
      'appointment_id' => $appointment_id,
      'service_id'     => (int)$existing['service_id'],
      'queue_date'     => (string)$existing['queue_date'],
      'category_id'    => (int)$existing['category_id'],
      'counter_id'     => isset($existing['counter_id']) ? (int)$existing['counter_id'] : null,
      'prefix'         => (string)$existing['prefix'],
      'queue_number'   => (int)$existing['queue_number'],
      'queue_code'     => (string)$existing['queue_code'],
      'status'         => (string)$existing['status'],
      'created_at'     => (string)$existing['created_at'],
    ];
  }

  $prefix = get_category_prefix($conn, $category_id);

  // Retry insert if duplicate queue happens
  for ($attempt = 0; $attempt < 10; $attempt++) {
    $queue_number = allocate_next_queue_number($conn, $queue_date, $category_id);
    $queue_code   = format_queue_code($prefix, $queue_number);

    $ins = $conn->prepare("
      INSERT INTO queue
        (appointment_id, service_id, queue_date, category_id, counter_id, prefix, queue_number, queue_code, status, created_at)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, 'waiting', NOW())
    ");

    if (!$ins) {
      throw new Exception("DB error (insert queue): " . $conn->error);
    }

    $ins->bind_param(
      "iisiisis",
      $appointment_id,
      $service_id,
      $queue_date,
      $category_id,
      $counter_id,
      $prefix,
      $queue_number,
      $queue_code
    );

    if ($ins->execute()) {
      $queue_id = (int)$conn->insert_id;
      $ins->close();

      return [
        'queue_id'       => $queue_id,
        'appointment_id' => $appointment_id,
        'service_id'     => $service_id,
        'queue_date'     => $queue_date,
        'category_id'    => $category_id,
        'counter_id'     => $counter_id,
        'prefix'         => $prefix,
        'queue_number'   => $queue_number,
        'queue_code'     => $queue_code,
        'status'         => 'waiting',
        'created_at'     => date('Y-m-d H:i:s'),
      ];
    }

    $errno = (int)$ins->errno;
    $err   = (string)$ins->error;
    $ins->close();

    if ($errno === 1062) {
      continue;
    }

    throw new Exception("Queue insert failed: " . $err);
  }

  throw new Exception("Failed to issue queue after multiple retries.");
}

/* =========================================================
   SAVE AUDIT LOG
   Records staff actions into audit_logs table
========================================================= */
function log_audit(
  mysqli $conn,
  string $action,
  string $details = "",
  ?int $queue_id = null,
  ?string $queue_code = null,
  ?int $category_id = null,
  ?int $counter_id = null,
  ?int $appointment_id = null
): void {

  // Add client info to details if appointment exists
  if ($appointment_id !== null && $appointment_id > 0) {
    $stmt_apt = $conn->prepare("
      SELECT ap.user_id, ap.walkin_name, ap.client_status, u.full_name
      FROM appointments ap
      LEFT JOIN users u ON u.user_id = ap.user_id
      WHERE ap.appointment_id = ?
      LIMIT 1
    ");

    if ($stmt_apt) {
      $stmt_apt->bind_param("i", $appointment_id);
      $stmt_apt->execute();
      $apt_result = $stmt_apt->get_result();

      if ($apt_result && ($apt_row = $apt_result->fetch_assoc())) {
        $client_name = (!empty($apt_row['user_id']) && !empty($apt_row['full_name']))
          ? (string)$apt_row['full_name']
          : (!empty($apt_row['walkin_name']) ? (string)$apt_row['walkin_name'] : 'Unknown');

        $client_status = !empty($apt_row['client_status']) ? (string)$apt_row['client_status'] : '';

        $details = ($details !== '')
          ? ($details . " | Client: " . $client_name)
          : ("Client: " . $client_name);

        if ($client_status !== '') {
          $details .= " | ClientStatus: " . $client_status;
        }
      }

      $stmt_apt->close();
    }
  }

  $stmt = $conn->prepare("
    INSERT INTO audit_logs
      (actor_user_id, actor_name, actor_role, action,
       queue_id, queue_code, category_id, counter_id,
       details, appointment_id, created_at)
    VALUES
      (?, ?, ?, ?,
       ?, ?, ?, ?,
       ?, ?, NOW())
  ");

  if (!$stmt) {
    throw new Exception("Audit Log Prepare Error: " . $conn->error);
  }

  $actor_user_id = (int)($_SESSION['user_id'] ?? 0);
  $actor_name    = (string)($_SESSION['name'] ?? 'System');
  $actor_role    = (string)($_SESSION['role'] ?? 'staff');

  $qid  = $queue_id;
  $qcod = $queue_code;
  $cid  = $category_id;
  $ctr  = $counter_id;
  $aid  = $appointment_id;

  $stmt->bind_param(
    "isssisiisi",
    $actor_user_id,
    $actor_name,
    $actor_role,
    $action,
    $qid,
    $qcod,
    $cid,
    $ctr,
    $details,
    $aid
  );

  if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    throw new Exception("Audit Log Error: " . $err);
  }

  $stmt->close();
}
