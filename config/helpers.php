<?php
// config/helpers.php

/* =========================================================
   SAFE TEXT OUTPUT
========================================================= */
function e(?string $str): string
{
  return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   REFERENCE CODE
========================================================= */
function generate_reference_code($appointment_date)
{
  return 'REF-' . date('Ymd', strtotime($appointment_date)) . '-' .
    strtoupper(substr(md5(uniqid()), 0, 4));
}

/* =========================================================
   QUEUE CODE FORMAT
========================================================= */
function format_queue_code(string $prefix, int $number): string
{
  $prefix = strtoupper(trim($prefix));
  return $prefix . str_pad((string)$number, 3, '0', STR_PAD_LEFT);
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
========================================================= */
function normalize_client_status(string $s): string
{
  $s = trim($s);

  $allowed = [
    'none',
    'senior',
    'pwd',
    'pregnant',
    'Regular',
    'Senior Citizen',
    'PWD',
    'Pregnant',
    'Special Lane'
  ];

  return in_array($s, $allowed, true) ? $s : 'none';
}

/* =========================================================
   MAP OLD / NEW STATUS TO PRIORITY TYPE
========================================================= */
function map_priority_status(string $client_status): string
{
  $client_status = normalize_client_status($client_status);

  $map = [
    'none' => 'none',
    'Regular' => 'none',
    'senior' => 'senior',
    'Senior Citizen' => 'senior',
    'pwd' => 'pwd',
    'PWD' => 'pwd',
    'pregnant' => 'pregnant',
    'Pregnant' => 'pregnant',
    'Special Lane' => 'pending_special'
  ];

  return $map[$client_status] ?? 'none';
}

/* =========================================================
   GET SERVICE INFO
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
   - Priority clients go to Counter 2 only after approval
   - Appointment and walk-in clients get counter later in serve.php
========================================================= */
function compute_counter_id(mysqli $conn, int $category_id, string $queue_type, string $verification_status): ?int
{
  if ($queue_type === 'priority' && $verification_status === 'approved') {
    return 2;
  }

  return null;
}

/* =========================================================
   COUNTER RULES USING SERVICE
========================================================= */
function compute_counter_id_from_service(mysqli $conn, int $service_id, int $category_id, string $queue_type, string $verification_status): ?int
{
  $svc = get_service_row($conn, $service_id);
  $cat = $category_id > 0 ? $category_id : (int)$svc['category_id'];

  return compute_counter_id($conn, $cat, $queue_type, $verification_status);
}

/* =========================================================
   ALLOCATE NEXT QUEUE NUMBER
   Shared by prefix + date:
   M = Membership
   B = Benefit Availment
   P = Priority
========================================================= */
function allocate_next_queue_number(mysqli $conn, string $queue_date, string $prefix): int
{
  $lockKey = "qnum:{$queue_date}:prefix{$prefix}";

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
    $stmt = $conn->prepare("
      SELECT COALESCE(MAX(queue_number), 0) AS mx
      FROM queue
      WHERE queue_date = ?
        AND prefix = ?
      FOR UPDATE
    ");

    if (!$stmt) {
      throw new Exception("DB error (max queue_number): " . $conn->error);
    }

    $stmt->bind_param("ss", $queue_date, $prefix);
    $stmt->execute();
    $mx = (int)($stmt->get_result()->fetch_assoc()['mx'] ?? 0);
    $stmt->close();

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
   DETERMINE QUEUE TYPE + PREFIX
========================================================= */
function determine_queue_meta(int $category_id, string $source, string $client_status): array
{
  $priority_status = map_priority_status($client_status);

  $queue_type = 'walkin';
  $verification_status = 'not_needed';

  if ($source === 'online') {
    $queue_type = 'appointment';
  }

  if (in_array($priority_status, ['senior', 'pwd', 'pregnant', 'pending_special'], true)) {
    $queue_type = 'priority';
    $verification_status = 'pending';
  }

  if ($queue_type === 'priority') {
    $prefix = 'P';
  } elseif ((int)$category_id === 1) {
    $prefix = 'M';
  } else {
    $prefix = 'B';
  }

  return [
    'queue_type' => $queue_type,
    'priority_status' => $priority_status === 'pending_special' ? 'none' : $priority_status,
    'verification_status' => $verification_status,
    'prefix' => $prefix,
  ];
}

/* =========================================================
   GET EXISTING QUEUE BY APPOINTMENT
========================================================= */
function get_existing_queue_by_appointment(mysqli $conn, int $appointment_id): ?array
{
  $stmt = $conn->prepare("
    SELECT
      queue_id,
      appointment_id,
      service_id,
      queue_date,
      category_id,
      counter_id,
      prefix,
      queue_number,
      queue_code,
      queue_type,
      priority_status,
      verification_status,
      checked_in_at,
      status,
      created_at
    FROM queue
    WHERE appointment_id = ?
    LIMIT 1
  ");

  if (!$stmt) {
    throw new Exception("DB error (queue lookup): " . $conn->error);
  }

  $stmt->bind_param("i", $appointment_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $row ?: null;
}

/* =========================================================
   ISSUE / RESERVE QUEUE
   - Online booking reserves queue number immediately
   - Online kiosk check-in reuses the same queue
   - Walk-in creates queue immediately with checked_in_at = NOW()
========================================================= */
function issue_queue_at_booking(mysqli $conn, int $appointment_id): array
{
  if ($appointment_id <= 0) {
    throw new Exception("Invalid appointment_id.");
  }

  $stmt = $conn->prepare("
    SELECT appointment_id, service_id, category_id, counter_id, client_status, appointment_date, source
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
  $client_status = (string)($apt['client_status'] ?? 'none');
  $queue_date    = (string)($apt['appointment_date']);
  $source        = (string)($apt['source'] ?? 'walkin');

  if ($service_id <= 0) {
    throw new Exception("Service missing on appointment.");
  }

  if ($queue_date === '') {
    throw new Exception("Appointment date missing.");
  }

  $svc = get_service_row($conn, $service_id);
  if ($category_id <= 0) {
    $category_id = (int)$svc['category_id'];
  }

  $meta = determine_queue_meta($category_id, $source, $client_status);
  $queue_type = $meta['queue_type'];
  $priority_status = $meta['priority_status'];
  $verification_status = $meta['verification_status'];
  $prefix = $meta['prefix'];

  $counter_id = compute_counter_id_from_service(
    $conn,
    $service_id,
    $category_id,
    $queue_type,
    $verification_status
  );

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

  $existing = get_existing_queue_by_appointment($conn, $appointment_id);

  if ($existing) {
    return [
      'queue_id'             => (int)$existing['queue_id'],
      'appointment_id'       => $appointment_id,
      'service_id'           => (int)$existing['service_id'],
      'queue_date'           => (string)$existing['queue_date'],
      'category_id'          => (int)$existing['category_id'],
      'counter_id'           => isset($existing['counter_id']) ? (int)$existing['counter_id'] : null,
      'prefix'               => (string)$existing['prefix'],
      'queue_number'         => (int)$existing['queue_number'],
      'queue_code'           => (string)$existing['queue_code'],
      'queue_type'           => (string)$existing['queue_type'],
      'priority_status'      => (string)$existing['priority_status'],
      'verification_status'  => (string)$existing['verification_status'],
      'checked_in_at'        => $existing['checked_in_at'] !== null ? (string)$existing['checked_in_at'] : null,
      'status'               => (string)$existing['status'],
      'created_at'           => (string)$existing['created_at'],
    ];
  }

  for ($attempt = 0; $attempt < 10; $attempt++) {
    $queue_number  = allocate_next_queue_number($conn, $queue_date, $prefix);
    $queue_code    = format_queue_code($prefix, $queue_number);
    $checked_in_at = ($source === 'online') ? null : date('Y-m-d H:i:s');

    $ins = $conn->prepare("
      INSERT INTO queue
      (
        appointment_id,
        service_id,
        queue_date,
        category_id,
        counter_id,
        prefix,
        queue_number,
        queue_code,
        queue_type,
        priority_status,
        verification_status,
        checked_in_at,
        status,
        created_at
      )
      VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting', NOW())
    ");

    if (!$ins) {
      throw new Exception("DB error (insert queue): " . $conn->error);
    }

    $ins->bind_param(
      "iisiisisssss",
      $appointment_id,
      $service_id,
      $queue_date,
      $category_id,
      $counter_id,
      $prefix,
      $queue_number,
      $queue_code,
      $queue_type,
      $priority_status,
      $verification_status,
      $checked_in_at
    );

    if ($ins->execute()) {
      $queue_id = (int)$conn->insert_id;
      $ins->close();

      return [
        'queue_id'             => $queue_id,
        'appointment_id'       => $appointment_id,
        'service_id'           => $service_id,
        'queue_date'           => $queue_date,
        'category_id'          => $category_id,
        'counter_id'           => $counter_id,
        'prefix'               => $prefix,
        'queue_number'         => $queue_number,
        'queue_code'           => $queue_code,
        'queue_type'           => $queue_type,
        'priority_status'      => $priority_status,
        'verification_status'  => $verification_status,
        'checked_in_at'        => $checked_in_at,
        'status'               => 'waiting',
        'created_at'           => date('Y-m-d H:i:s'),
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

