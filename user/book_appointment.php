<?php
// Start user session
session_name('user_session');
session_start();

// Load needed files
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/csrf.php";
require_once __DIR__ . "/../config/helpers.php";

// Set local time
date_default_timezone_set('Asia/Manila');

// Allow only logged-in users
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
  header("Location: login.php");
  exit();
}

// Basic user info
$user_id = (int)($_SESSION['user_id'] ?? 0);
$today   = date('Y-m-d');

// Read success and error messages from session
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Load active services
$services = $conn->query("
  SELECT s.service_id, s.service_name, qc.category_id, qc.category_name
  FROM services s
  JOIN queue_categories qc ON qc.category_id = s.category_id
  WHERE s.is_active = 1
  ORDER BY qc.category_id, s.service_id
");

/* =========================================================
   HELPERS
========================================================= */

function map_client_status_label(string $raw): string
{
  $raw = trim(strtolower($raw));

  return match ($raw) {
    'senior' => 'Senior Citizen',
    'pregnant' => 'Pregnant',
    'pwd' => 'PWD',
    default => 'Regular',
  };
}

function map_client_status_key(string $label): string
{
  $label = trim(strtolower($label));

  return match ($label) {
    'senior citizen' => 'senior',
    'pregnant' => 'pregnant',
    'pwd' => 'pwd',
    default => 'regular',
  };
}

/* =========================================================
   HANDLE FORM SUBMIT
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
  csrf_validate();

  $service_id         = (int)($_POST['service_id'] ?? 0);
  $appointment_date   = trim((string)($_POST['appointment_date'] ?? ''));
  $appointment_time   = trim((string)($_POST['appointment_time'] ?? ''));
  $client_status_raw  = trim((string)($_POST['client_status'] ?? 'regular'));
  $client_status      = map_client_status_label($client_status_raw);

  /* =========================================================
     BLOCK SATURDAY AND SUNDAY
  ========================================================= */
  $dayOfWeek = date('N', strtotime($appointment_date)); // 6 = Saturday, 7 = Sunday
  if ($dayOfWeek >= 6) {
    $_SESSION['error'] = "Appointments are only allowed from Monday to Friday.";
    header("Location: book_appointment.php");
    exit();
  }

  /* =========================================================
     BLOCK TIME THAT ALREADY PASSED
  ========================================================= */
  if ($appointment_date === $today) {
    $slotStart = strtotime($appointment_date . ' ' . $appointment_time);
    $slotEnd   = strtotime('+1 hour', $slotStart);
    $currentDateTime = time();

    // Block only if the whole slot already ended
    if ($slotEnd <= $currentDateTime) {
      $_SESSION['error'] = "The selected appointment time has already ended. Please choose another available slot.";
      header("Location: book_appointment.php");
      exit();
    }
  }

  if ($service_id <= 0 || $appointment_date === '' || $appointment_time === '') {
    $_SESSION['error'] = "Please complete all required fields.";
    header("Location: book_appointment.php");
    exit();
  }

  $d = DateTime::createFromFormat('Y-m-d', $appointment_date);
  if (!$d || $d->format('Y-m-d') !== $appointment_date) {
    $_SESSION['error'] = "Invalid appointment date.";
    header("Location: book_appointment.php");
    exit();
  }

  if ($appointment_date < $today) {
    $_SESSION['error'] = "Cannot book appointments in the past.";
    header("Location: book_appointment.php");
    exit();
  }

  $t = DateTime::createFromFormat('H:i:s', $appointment_time);
  if (!$t) {
    $t = DateTime::createFromFormat('H:i', $appointment_time);
  }
  if (!$t) {
    $_SESSION['error'] = "Invalid appointment time.";
    header("Location: book_appointment.php");
    exit();
  }

  $appointment_time_db = $t->format('H:i:s');

  if ($appointment_time_db < "08:00:00" || $appointment_time_db > "17:00:00") {
    $_SESSION['error'] = "Appointment time must be within office hours (08:00 - 17:00).";
    header("Location: book_appointment.php");
    exit();
  }

  // Check if service exists and is active
  $stmt = $conn->prepare("
    SELECT service_id, category_id
    FROM services
    WHERE service_id = ? AND is_active = 1
    LIMIT 1
  ");
  $stmt->bind_param("i", $service_id);
  $stmt->execute();
  $service = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$service) {
    $_SESSION['error'] = "Selected service not found.";
    header("Location: book_appointment.php");
    exit();
  }

  $category_id = (int)$service['category_id'];

  // Keep counter NULL for booked users; counter is assigned later in serve.php
  $counter_id = null;

  // Duplicate same service / same date
  $stmt = $conn->prepare("
    SELECT appointment_id
    FROM appointments
    WHERE user_id = ? AND service_id = ? AND appointment_date = ?
      AND status IN ('booked', 'checked_in')
    LIMIT 1
  ");
  $stmt->bind_param("iis", $user_id, $service_id, $appointment_date);
  $stmt->execute();
  $dup = $stmt->get_result()->num_rows > 0;
  $stmt->close();

  if ($dup) {
    $_SESSION['error'] = "You already booked this service on that date. Please choose another date.";
    header("Location: book_appointment.php");
    exit();
  }

  /* =========================================================
     SLOT AVAILABILITY CHECK
  ========================================================= */
  $client_type_key = map_client_status_key($client_status);

  $stmt = $conn->prepare("
    SELECT
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
      AND l.slot_time = ?
    GROUP BY l.max_capacity
    LIMIT 1
  ");
  $stmt->bind_param("siss", $appointment_date, $category_id, $client_type_key, $appointment_time_db);
  $stmt->execute();
  $slotRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$slotRow) {
    $_SESSION['error'] = "The selected time slot is not configured for this service or client status.";
    header("Location: book_appointment.php");
    exit();
  }

  if ((int)$slotRow['booked_count'] >= (int)$slotRow['max_capacity']) {
    $_SESSION['error'] = "This time slot is no longer available. Please choose another time.";
    header("Location: book_appointment.php");
    exit();
  }

  // Limit online client to maximum of 3 bookings for the same date
  $stmt = $conn->prepare("
    SELECT COUNT(*) AS total_bookings
    FROM appointments
    WHERE user_id = ?
      AND appointment_date = ?
      AND status IN ('booked', 'checked_in')
  ");
  $stmt->bind_param("is", $user_id, $appointment_date);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ((int)($row['total_bookings'] ?? 0) >= 3) {
    $_SESSION['error'] = "You have already reached the maximum of 3 bookings for this date.";
    header("Location: book_appointment.php");
    exit();
  }

  /* =========================================================
     SAVE BOOKING + RESERVE QUEUE NUMBER
  ========================================================= */
  $conn->begin_transaction();

  try {
    $reference_code = generate_reference_code($appointment_date);

    for ($i = 0; $i < 5; $i++) {
      $chk = $conn->prepare("SELECT appointment_id FROM appointments WHERE reference_code = ? LIMIT 1");
      if (!$chk) {
        throw new Exception("Failed to validate reference code.");
      }

      $chk->bind_param("s", $reference_code);
      $chk->execute();
      $exists = $chk->get_result()->num_rows > 0;
      $chk->close();

      if (!$exists) {
        break;
      }

      $reference_code = generate_reference_code($appointment_date);
    }

    // Insert appointment
    $stmt = $conn->prepare("
      INSERT INTO appointments
        (reference_code, user_id, service_id, category_id, counter_id, client_status, appointment_date, appointment_time, source, status)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, 'online', 'booked')
    ");
    if (!$stmt) {
      throw new Exception("Failed to prepare appointment insert.");
    }

    $stmt->bind_param(
      "siiiisss",
      $reference_code,
      $user_id,
      $service_id,
      $category_id,
      $counter_id,
      $client_status,
      $appointment_date,
      $appointment_time_db
    );
    $stmt->execute();
    $appointment_id = (int)$conn->insert_id;
    $stmt->close();

    // Reserve queue number immediately for online booking
    $q = issue_queue_at_booking($conn, $appointment_id);

    log_audit(
      $conn,
      'book_appointment',
      'Appointment booked successfully',
      $q['queue_id'] ?? null,
      $q['queue_code'] ?? null,
      $category_id,
      null,
      $appointment_id
    );

    $conn->commit();

    $_SESSION['success'] = "Appointment booked successfully! Reference Code: {$reference_code}. Your reserved queue number is {$q['queue_code']}. Please check-in at the kiosk on your appointment date.";
    header("Location: book_appointment.php");
    exit();
  } catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['error'] = "Booking failed: " . $e->getMessage();
    header("Location: book_appointment.php");
    exit();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Book Appointment — PhilHealth</title>

  <script src="https://cdn.tailwindcss.com"></script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --g-dark: #1a4d2e;
      --g-mid: #2d7a3a;
      --g-main: #4caf50;
      --gold: #e6a817;
      --gold-dk: #c8900c;
      --card: rgba(255, 255, 255, 0.93);
      --border: rgba(255, 255, 255, 0.72);
      --shadow: 0 2px 16px rgba(30, 90, 30, 0.10);
      --shadow-lg: 0 8px 40px rgba(30, 90, 30, 0.15);
      --t-dark: #14381e;
      --t-mid: #2a5a32;
      --t-soft: #4a7a52;
      --t-muted: #6b9a72;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      background: linear-gradient(150deg, #c9eda0 0%, #9ed654 35%, #72c435 65%, #4fa828 100%);
      color: var(--t-dark);
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(rgba(255, 255, 255, 0.14) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.14) 1px, transparent 1px);
      background-size: 52px 52px;
      mask-image: radial-gradient(ellipse 80% 55% at 50% 0%, black 40%, transparent 80%);
      opacity: 0.35;
      pointer-events: none;
    }

    header {
      border-bottom: 1px solid rgba(255, 255, 255, 0.40);
      background: rgba(255, 255, 255, 0.20);
      backdrop-filter: blur(20px);
    }

    .hdr {
      max-width: 720px;
      margin: 0 auto;
      padding: 0 1.5rem;
      height: 62px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 700;
      color: var(--g-dark);
      text-decoration: none;
      padding: 7px 14px;
      border-radius: 10px;
      border: 1.5px solid rgba(255, 255, 255, 0.6);
      background: rgba(255, 255, 255, 0.45);
      transition: background .15s;
    }

    .back-btn:hover {
      background: rgba(255, 255, 255, 0.65);
    }

    .date-lbl {
      font-size: 12px;
      font-weight: 600;
      color: var(--g-dark);
      opacity: .65;
    }

    main {
      max-width: 720px;
      margin: 0 auto;
      padding: 2.5rem 1.5rem;
    }

    .form-card {
      background: var(--card);
      border-radius: 24px;
      border: 1px solid var(--border);
      box-shadow: var(--shadow-lg);
      overflow: hidden;
      backdrop-filter: blur(8px);
    }

    .card-stripe {
      height: 5px;
      background: linear-gradient(90deg, var(--g-dark), var(--g-main), var(--gold));
    }

    .card-body {
      padding: 2rem;
    }

    .card-title {
      font-size: clamp(1.4rem, 3vw, 1.9rem);
      font-weight: 800;
      color: var(--t-dark);
      letter-spacing: -.02em;
    }

    .card-sub {
      margin-top: .5rem;
      font-size: 13.5px;
      color: var(--t-soft);
    }

    .alert {
      margin-top: 1.25rem;
      padding: 1rem;
      border-radius: 14px;
      font-size: 13.5px;
    }

    .alert-ok {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #166534;
    }

    .alert-err {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #991b1b;
    }

    .alert-title {
      font-weight: 800;
      margin-bottom: 4px;
    }

    .dt-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    @media(max-width:480px) {
      .dt-grid {
        grid-template-columns: 1fr;
      }
    }

    .f-lbl {
      display: block;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: var(--t-soft);
      margin-bottom: 8px;
    }

    .f-inp {
      width: 100%;
      background: #f5fbf5;
      border: 1.5px solid #cce8cc;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 13.5px;
      font-weight: 500;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--t-dark);
      outline: none;
      transition: border-color .15s, background .15s;
      appearance: none;
      -webkit-appearance: none;
    }

    .f-inp:focus {
      border-color: var(--g-main);
      background: #edf9ed;
    }

    .f-inp:disabled {
      opacity: .45;
      cursor: not-allowed;
      background: #f0f7f0;
    }

    .f-hint {
      margin-top: 5px;
      font-size: 11.5px;
      color: var(--t-muted);
    }

    input[type="date"] {
      color-scheme: light;
    }

    .btn-submit {
      display: block;
      width: 100%;
      background: var(--g-dark);
      border: none;
      color: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 800;
      font-size: 15px;
      padding: 14px;
      border-radius: 14px;
      cursor: pointer;
      transition: background .15s, transform .12s;
      margin-top: .25rem;
      box-shadow: 0 4px 16px rgba(20, 80, 20, 0.2);
    }

    .btn-submit:hover {
      background: var(--g-mid);
    }

    .btn-submit:active {
      transform: scale(.99);
    }

    .divider {
      height: 1px;
      background: #e8f5e8;
      margin: 1.5rem 0;
    }

    .form-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .foot-link {
      font-size: 13px;
      font-weight: 700;
      color: var(--g-mid);
      text-decoration: none;
    }

    .foot-link:hover {
      text-decoration: underline;
    }
  </style>
</head>

<body>
  <header>
    <div class="hdr">
      <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
      <span class="date-lbl">Today: <?php echo date('M d, Y'); ?></span>
    </div>
  </header>

  <main>
    <div class="form-card">
      <div class="card-stripe"></div>

      <div class="card-body">
        <div class="card-title">Book an Appointment</div>
        <div class="card-sub">
          Reservation confirmed — your queue number is generated upon booking. Please check in on your appointment date to validate your slot. Online bookings are prioritized over walk-ins.
        </div>

        <?php if ($success): ?>
          <div class="alert alert-ok">
            <div class="alert-title">Booking Confirmed</div>
            <?php echo e($success); ?>
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-err">
            <div class="alert-title">Error</div>
            <?php echo e($error); ?>
          </div>
        <?php endif; ?>

        <form method="POST" id="apptForm">
          <?php echo csrf_field(); ?>

          <div class="space-y-1">
            <label for="service_select" class="block text-sm font-medium text-slate-800">Select Service *</label>

            <div class="relative">
              <select
                id="service_select"
                name="service_id"
                required
                class="w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 py-3 pr-12 text-slate-900 shadow-sm focus:outline-none focus:ring-4 focus:ring-slate-200 focus:border-slate-400">
                <option value="">— Choose a Service —</option>

                <?php
                $cur = '';
                if ($services) {
                  while ($row = $services->fetch_assoc()):
                    if ($cur !== $row['category_name']) {
                      if ($cur !== '') echo '</optgroup>';
                      $cur = $row['category_name'];

                      $label = $cur;
                      if ($cur === 'Membership') {
                        $label = 'Membership (M)';
                      } elseif ($cur === 'Benefit Availment') {
                        $label = 'Benefit Availment (B)';
                      }

                      echo '<optgroup label="' . e($label) . '">';
                    }
                ?>
                    <option value="<?php echo (int)$row['service_id']; ?>">
                      <?php echo e($row['service_name']); ?>
                    </option>
                <?php
                  endwhile;
                  if ($cur !== '') echo '</optgroup>';
                }
                ?>
              </select>

              <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-500">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24">
                  <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </div>
            </div>

            <p class="text-xs text-slate-500">Choose a service to enable date &amp; time.</p>
          </div>

          <div class="space-y-1 mt-4">
            <label for="client_status" class="block text-sm font-medium text-slate-800">Client Status *</label>

            <div class="relative">
              <select
                id="client_status"
                name="client_status"
                required
                class="w-full appearance-none rounded-xl border border-slate-200 bg-white px-4 py-3 pr-12 text-slate-900 shadow-sm focus:outline-none focus:ring-4 focus:ring-slate-200 focus:border-slate-400">
                <option value="regular">Regular</option>
                <option value="senior">Senior Citizen</option>
                <option value="pregnant">Pregnant</option>
                <option value="pwd">PWD</option>
              </select>

              <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-500">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24">
                  <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </div>
            </div>

            <p class="text-xs text-amber-700 mt-1">
              Special-lane reservations are subject to slot availability and staff verification upon check-in.
              <br>Priority routing: Special Lane (Membership) and (Benefit Availment) go to Counter 2.
            </p>
          </div>

          <div class="dt-grid mt-4">
            <div class="field" style="margin-bottom:0;">
              <label for="appointment_date" class="f-lbl">Appointment Date *</label>
              <input
                type="date"
                id="appointment_date"
                name="appointment_date"
                required
                min="<?php echo e($today); ?>"
                disabled
                class="f-inp">
            </div>

            <div class="field" style="margin-bottom:0;">
              <label for="appointment_time" class="f-lbl">Preferred Time *</label>
              <select
                id="appointment_time"
                name="appointment_time"
                required
                disabled
                class="f-inp">
                <option value="">-- Select Time --</option>
              </select>
              <p class="f-hint">Only available slots will appear here.</p>
            </div>
          </div>

          <div style="margin-top:1.5rem;">
            <button type="submit" name="book" class="btn-submit">Confirm Reservation</button>
          </div>

          <div class="divider"></div>

          <div class="form-footer">
            <a href="my_appointments.php" class="foot-link">View My Appointments →</a>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const serviceSelect = document.getElementById('service_select');
      const clientStatus = document.getElementById('client_status');
      const dateInput = document.getElementById('appointment_date');
      const timeSelect = document.getElementById('appointment_time');

      function resetTimeOptions(message = '-- Select Time --') {
        timeSelect.innerHTML = `<option value="">${message}</option>`;
      }

      function setEnabled(on) {
        dateInput.disabled = !on;
        timeSelect.disabled = !on;

        if (!on) {
          dateInput.value = '';
          resetTimeOptions();
        }
      }

      async function loadAvailableTimes() {
        const serviceId = serviceSelect.value;
        const clientType = clientStatus.value;
        const appointmentDate = dateInput.value;

        if (!serviceId || !clientType || !appointmentDate) {
          resetTimeOptions('-- Select Time --');
          timeSelect.disabled = true;
          return;
        }

        try {
          const res = await fetch(
            `get_available_slots.php?service_id=${encodeURIComponent(serviceId)}&client_status=${encodeURIComponent(clientType)}&date=${encodeURIComponent(appointmentDate)}`, {
              cache: 'no-store'
            }
          );

          const data = await res.json();

          if (!Array.isArray(data) || data.length === 0) {
            resetTimeOptions('No available slots');
            timeSelect.disabled = true;
            return;
          }

          resetTimeOptions('-- Select Time --');
          data.forEach(slot => {
            const opt = document.createElement('option');
            opt.value = slot.slot_time;
            opt.textContent = slot.label ?? slot.slot_time;
            timeSelect.appendChild(opt);
          });
          timeSelect.disabled = false;
        } catch (err) {
          resetTimeOptions('Failed to load slots');
          timeSelect.disabled = true;
        }
      }

      setEnabled(!!serviceSelect.value);

      serviceSelect.addEventListener('change', () => {
        setEnabled(!!serviceSelect.value);
        loadAvailableTimes();
      });

      clientStatus.addEventListener('change', loadAvailableTimes);
      dateInput.addEventListener('change', loadAvailableTimes);
    });
  </script>

  <script>
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }
  </script>
</body>

</html>
