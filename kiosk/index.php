<?php
// kiosk/index.php

// Start staff session
session_name('staff_session');
session_start();

// Allow only staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
  header("Location: ../staff/login.php");
  exit();
}

// Load needed files
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/csrf.php";
require_once __DIR__ . "/../config/helpers.php";

// Show errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set local time
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// Success and error messages
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/* =========================================================
   Load services for dropdown
   ========================================================= */
$services_by_cat = [];

$svc = $conn->query("
  SELECT
    s.service_id,
    s.service_name,
    s.category_id,
    qc.category_name,
    qc.prefix
  FROM services s
  JOIN queue_categories qc ON qc.category_id = s.category_id
  WHERE s.is_active = 1
    AND qc.is_active = 1
ORDER BY
s.category_id,
CASE
WHEN s.service_name = 'Membership Registration' THEN 1
WHEN s.service_name = 'Membership Renewal' THEN 2
WHEN s.service_name = 'Amendment of Member Data Record' THEN 3
WHEN s.service_name = 'Hospitalization Admission' THEN 4
WHEN s.service_name = 'Benefit Coverage Assesment' THEN 5
WHEN s.service_name = 'Other Benefit Claims' THEN 6
ELSE 7
END,
    s.service_name ASC
");

if ($svc) {
  while ($r = $svc->fetch_assoc()) {
    $services_by_cat[(int)$r['category_id']][] = $r;
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin_appointment'])) {

  /* =========================================================
   CHECK OFFICE HOURS
========================================================= */
  $current_time = strtotime(date('H:i'));
  $start_time   = strtotime('08:00');
  $end_time     = strtotime('17:00');

  if ($current_time < $start_time || $current_time > $end_time) {
    $_SESSION['error'] = "Online appointment check-in is allowed only from 8:00 AM to 5:00 PM.";
    header("Location: index.php");
    exit();
  }

  /* =========================================================
   BLOCK WEEKEND CHECK-IN
========================================================= */
  $dayOfWeek = date('N'); // 6 = Saturday, 7 = Sunday

  if ($dayOfWeek >= 6) {
    $_SESSION['error'] = "Check-in is not allowed on Saturday and Sunday.";
    header("Location: index.php");
    exit();
  }

  csrf_validate();

  $ref    = trim((string)($_POST['reference_code'] ?? ''));
  $mobile = trim((string)($_POST['mobile_number'] ?? ''));

  if ($ref === '' && $mobile === '') {
    $_SESSION['error'] = "Enter Reference Code or Mobile Number.";
    header("Location: index.php");
    exit();
  }

  if ($ref !== '') {
    $stmt = $conn->prepare("
      SELECT a.*
      FROM appointments a
      WHERE a.reference_code = ?
        AND a.appointment_date = ?
        AND a.status = 'booked'
        AND a.source = 'online'
      LIMIT 1
    ");
    $stmt->bind_param("ss", $ref, $today);
  } else {
    $stmt = $conn->prepare("
      SELECT a.*
      FROM appointments a
      JOIN users u ON u.user_id = a.user_id
      WHERE u.mobile_number = ?
        AND a.appointment_date = ?
        AND a.status = 'booked'
        AND a.source = 'online'
      ORDER BY a.created_at DESC
      LIMIT 1
    ");
    $stmt->bind_param("ss", $mobile, $today);
  }

  $stmt->execute();
  $appt = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$appt) {
    $_SESSION['error'] = "No valid booked appointment found for today.";
    header("Location: index.php");
    exit();
  }

  /* =========================================================
     CHECK IF APPOINTMENT TIME ALREADY PASSED
  ========================================================= */
  $appointment_datetime = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);

  if (time() > $appointment_datetime) {
    $_SESSION['error'] = "Your appointment time has already passed.";
    header("Location: index.php");
    exit();
  }

  $appointment_id = (int)$appt['appointment_id'];
  $service_id     = (int)$appt['service_id'];
  $category_id    = (int)$appt['category_id'];
  $client_status  = normalize_client_status((string)($appt['client_status'] ?? 'Regular'));

  if ($service_id <= 0) {
    $_SESSION['error'] = "Appointment has no service assigned.";
    header("Location: index.php");
    exit();
  }

  $counter_id = compute_counter_id_from_service($conn, $service_id, $category_id, $client_status);

  $conn->begin_transaction();

  try {
    $up = $conn->prepare("
      UPDATE appointments
      SET counter_id = ?, status='checked_in', arrival_time=NOW()
      WHERE appointment_id = ?
    ");
    $up->bind_param("ii", $counter_id, $appointment_id);
    $up->execute();
    $up->close();

    $q = issue_queue_at_booking($conn, $appointment_id);

    $conn->commit();

    $_SESSION['success'] =
      "Checked-in successfully! Queue Number: {$q['queue_code']} (Counter {$q['counter_id']})";
  } catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['error'] = "Check-in failed: " . $e->getMessage();
  }

  header("Location: index.php");
  exit();
}

/* =========================================================
   Walk-in registration
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin_walkin'])) {

  /* =========================================================
     CHECK WALK-IN OFFICE HOURS
     Walk-in is allowed only from 8:00 AM to 5:00 PM
  ========================================================= */

  $current_time = strtotime(date('H:i'));
  $start_time   = strtotime('08:00');
  $end_time     = strtotime('17:00');

  if ($current_time < $start_time || $current_time > $end_time) {
    $_SESSION['error'] = "Walk-in registration is allowed only from 8:00 AM to 5:00 PM.";
    header("Location: index.php");
    exit();
  }

  csrf_validate();
  csrf_validate();

  $service_id    = (int)($_POST['service_id'] ?? 0);
  $walkin_name   = trim((string)($_POST['walkin_name'] ?? ''));
  $walkin_mobile = trim((string)($_POST['walkin_mobile'] ?? ''));
  $client_status = normalize_client_status((string)($_POST['client_status'] ?? 'Regular'));

  if ($service_id <= 0) {
    $_SESSION['error'] = "Please select a service for walk-in.";
    header("Location: index.php");
    exit();
  }

  if ($walkin_name === '') {
    $_SESSION['error'] = "Please enter your full name.";
    header("Location: index.php");
    exit();
  }

  /* Require at least First Name and Last Name */
  if (!preg_match('/^[A-Za-z]+(\s[A-Za-z]+)+$/', $walkin_name)) {
    $_SESSION['error'] = "Please enter your complete name (First Name and Last Name).";
    header("Location: index.php");
    exit();
  }

  $stmt = $conn->prepare("
    SELECT service_id, category_id
    FROM services
    WHERE service_id = ? AND is_active = 1
    LIMIT 1
  ");
  if (!$stmt) {
    $_SESSION['error'] = "Database error while loading service.";
    header("Location: index.php");
    exit();
  }

  $stmt->bind_param("i", $service_id);
  $stmt->execute();
  $svcRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$svcRow) {
    $_SESSION['error'] = "Service not found.";
    header("Location: index.php");
    exit();
  }

  $category_id = (int)$svcRow['category_id'];

  // Get proper counter based on service and client status
  $counter_id = compute_counter_id_from_service($conn, $service_id, $category_id, $client_status);

  $conn->begin_transaction();

  try {
    $reference_code = generate_reference_code($appointment_date);

    $stmt = $conn->prepare("
      INSERT INTO appointments
      (
        reference_code,
        user_id,
        walkin_name,
        walkin_mobile,
        service_id,
        category_id,
        counter_id,
        client_status,
        appointment_date,
        appointment_time,
        arrival_time,
        source,
        status,
        created_at
      )
      VALUES
      (
        ?, NULL, ?, ?, ?, ?, ?, ?,
        ?, NULL, NOW(), 'walkin', 'checked_in', NOW()
      )
    ");
    if (!$stmt) {
      throw new Exception("DB error (insert appointment): " . $conn->error);
    }

    $stmt->bind_param(
      "sssiiiss",
      $reference_code,
      $walkin_name,
      $walkin_mobile,
      $service_id,
      $category_id,
      $counter_id,
      $client_status,
      $today
    );

    $stmt->execute();
    $appointment_id = (int)$conn->insert_id;
    $stmt->close();

    // Create queue number
    $q = issue_queue_at_booking($conn, $appointment_id);

    $conn->commit();

    $_SESSION['success'] = "Walk-in registered! Your queue number is {$q['queue_code']}";
    header("Location: index.php");
    exit();
  } catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['error'] = "Walk-in registration failed: " . $e->getMessage();
    header("Location: index.php");
    exit();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Office Check-in Terminal</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: "#F7F2D1",
              100: "#D9FF8C",
              200: "#B6F45A",
              300: "#97EB2F",
              400: "#79E114",
              500: "#5FD000",
              700: "#1F723B",
              gold: "#E4B519",
              gold2: "#D29910"
            }
          }
        }
      }
    }
  </script>

  <style>
    .lime-bg {
      background: linear-gradient(180deg, #D9FF8C 0%, #B6F45A 35%, #97EB2F 65%, #5FD000 100%);
    }

    .grid-overlay {
      background-image:
        linear-gradient(to right, rgba(255, 255, 255, 0.18) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.18) 1px, transparent 1px);
      background-size: 56px 56px;
      mask-image: radial-gradient(70% 60% at 50% 20%, black 40%, transparent 80%);
      opacity: 0.25;
      pointer-events: none;
    }
  </style>
</head>

<body class="min-h-screen px-4 py-8 text-slate-900">
  <div class="fixed inset-0 -z-10 lime-bg"></div>
  <div class="fixed inset-0 -z-10 grid-overlay"></div>

  <div class="relative max-w-6xl mx-auto space-y-6">
    <header class="bg-white/80 backdrop-blur-xl rounded-3xl border border-white/60 shadow-xl p-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-start gap-3">
          <div class="h-10 w-10 rounded-2xl bg-brand-50 flex items-center justify-center overflow-hidden border border-white/50">
            <img src="../logo/philhealth_Logo.png" alt="PhilHealth Logo" class="h-10 w-10 object-contain">
          </div>

          <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/80 px-3 py-1 text-xs font-semibold text-slate-700">
              Check-in Terminal <span class="h-2 w-2 rounded-full bg-brand-700"></span> FCFS
            </div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">PhilHealth Office Check-in</h1>
            <p class="mt-1 text-sm text-slate-700/80">Check in to receive your queue number. Queue numbering is per category.</p>
          </div>
        </div>

        <div class="text-sm text-slate-700">
          <div class="rounded-2xl border border-white/60 bg-white/85 px-4 py-3 shadow-sm">
            <div class="text-xs text-slate-500">Today</div>
            <div class="font-semibold text-slate-900"><?php echo e(date('M d, Y')); ?></div>
            <div class="mt-1 text-xs text-slate-500">
              Signed in:
              <span class="font-semibold text-slate-800"><?php echo e($_SESSION['name'] ?? 'Staff'); ?></span>
            </div>
          </div>
        </div>
      </div>
    </header>

    <?php if ($success): ?>
      <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-900 shadow-sm">
        <div class="font-semibold">Success</div>
        <div class="mt-1"><?php echo e($success); ?></div>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-rose-900 shadow-sm">
        <div class="font-semibold">Error</div>
        <div class="mt-1"><?php echo e($error); ?></div>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Appointment Check-in -->
      <section class="bg-white/80 backdrop-blur-xl rounded-3xl border border-white/60 shadow-xl p-6">
        <h2 class="text-xl font-semibold tracking-tight text-slate-900">Appointment Check-in</h2>
        <p class="text-sm text-slate-700/80 mt-1">Enter a reference code or a registered mobile number.</p>

        <form method="POST" class="mt-5 space-y-4">
          <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

          <div>
            <label class="block text-sm font-medium text-slate-800 mb-1">Reference Code</label>
            <input
              name="reference_code"
              placeholder="REF-YYYYMMDD-XXXX"
              class="w-full rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 text-slate-900 placeholder-slate-400 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60">
          </div>

          <div class="flex items-center gap-3 py-1">
            <div class="h-px flex-1 bg-white/60"></div>
            <span class="text-xs font-semibold text-slate-600">OR</span>
            <div class="h-px flex-1 bg-white/60"></div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-800 mb-1">Mobile Number</label>
            <input
              name="mobile_number"
              placeholder="09xxxxxxxxx"
              inputmode="numeric"
              class="w-full rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 text-slate-900 placeholder-slate-400 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60">
            <p class="mt-1 text-xs text-slate-600">Use the mobile number saved in the user account.</p>
          </div>

          <button
            type="submit"
            name="checkin_appointment"
            class="w-full rounded-2xl bg-brand-700 px-4 py-3 font-semibold text-white shadow-md transition hover:opacity-95 focus:outline-none focus:ring-4 focus:ring-white/60 active:translate-y-[1px]">
            Check-in & Get Queue Number
          </button>
        </form>
      </section>

      <!-- Walk-in Registration -->
      <section class="bg-white/80 backdrop-blur-xl rounded-3xl border border-white/60 shadow-xl p-6">
        <h2 class="text-xl font-semibold tracking-tight text-slate-900">Walk-in Registration</h2>
        <p class="text-sm text-slate-700/80 mt-1">Select a service to register and receive a queue number.</p>

        <form method="POST" class="mt-6 space-y-5" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

          <div>
            <label for="service_id" class="block text-sm font-medium text-slate-800 mb-1">
              Select Service <span class="text-rose-600">*</span>
            </label>

            <div class="relative">
              <select
                id="service_id"
                name="service_id"
                required
                class="w-full appearance-none rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-12 text-slate-900 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60">
                <option value="">-- Choose a Service --</option>

                <?php foreach ($services_by_cat as $cat_id => $rows): ?>
                  <?php
                  $catName = $rows[0]['category_name'] ?? '';
                  $pref    = $rows[0]['prefix'] ?? '';
                  ?>
                  <optgroup label="<?php echo e($catName . ' (' . $pref . ')'); ?>">
                    <?php foreach ($rows as $s): ?>
                      <option value="<?php echo (int)$s['service_id']; ?>">
                        <?php echo e($s['service_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>

              <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-500">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
            </div>
          </div>

          <div>
            <label for="client_status" class="block text-sm font-medium text-slate-800 mb-1">Client Status</label>
            <select
              id="client_status"
              name="client_status"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60">
              <option value="Regular">Regular</option>
              <option value="Special Lane">Special Lane (PWD, Senior Citizen, and Pregnant)</option>
            </select>

            <p class="mt-1 text-xs text-slate-600">
              Priority routing: Special Lane goes to Counter 2.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="walkin_name" class="block text-sm font-medium text-slate-800 mb-1">Full Name *</label>
              <input
                id="walkin_name"
                type="text"
                name="walkin_name"
                required
                pattern="^[A-Za-z]+(\s[A-Za-z]+)+$"
                title="Please enter your full name (First Name and Last Name)"
                placeholder="e.g. Juan Dela Cruz"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60">
            </div>

            <div>
              <label for="walkin_mobile" class="block text-sm font-medium text-slate-800 mb-1">Mobile (optional)</label>
              <input
                id="walkin_mobile"
                type="tel"
                name="walkin_mobile"
                inputmode="numeric"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60">
            </div>
          </div>

          <button
            type="submit"
            name="checkin_walkin"
            class="w-full rounded-2xl bg-brand-gold px-4 py-3 font-extrabold text-slate-900 shadow-md transition hover:opacity-95">
            Register Walk-in & Get Queue Number
          </button>
        </form>
      </section>

    </div>

    <footer class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
      <a
        class="inline-flex items-center justify-center rounded-xl border-2 border-black bg-white/85 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-white transition"
        href="../staff/serve.php">
        Go to Staff Dashboard
      </a>

      <div class="text-xs text-slate-700/80">Tip: Use Reference Code for faster check-in.</div>
    </footer>
  </div>

  <script>
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }
  </script>
</body>

</html>