<?php
// Start user session
session_name('user_session');
session_start();

// Load needed files
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/helpers.php";

// Set local time
date_default_timezone_set('Asia/Manila');

// Allow only logged-in users
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
  header("Location: login.php");
  exit();
}

// User info and current date/time
$user_id = (int) $_SESSION['user_id'];
$today = date('Y-m-d');
$nowTime = date('H:i:s');

// Get filter values
$hist_status = strtolower(trim($_GET['hist_status'] ?? 'all'));
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

// Check date format
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
  $from = '';
}

if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
  $to = '';
}

// Swap dates if from is later than to
if ($from !== '' && $to !== '' && $from > $to) {
  [$from, $to] = [$to, $from];
}

// Mark missed booked appointments as no_show
$mark = $conn->prepare("
  UPDATE appointments
  SET status = 'no_show'
  WHERE user_id = ?
    AND LOWER(status) = 'booked'
    AND (
      appointment_date < ?
      OR (appointment_date = ? AND appointment_time < ?)
    )
");
$mark->bind_param("isss", $user_id, $today, $today, $nowTime);
$mark->execute();

// Get upcoming appointments
$upStmt = $conn->prepare("
  SELECT
    a.appointment_id,
    a.reference_code,
    a.appointment_date,
    a.appointment_time,
    a.arrival_time,
    a.status AS appt_status,
    a.source,
    s.service_name,
    qc.category_name,
    qc.prefix,
    q.queue_code,
    q.queue_number,
    q.status AS queue_status,
    sc.counter_name
  FROM appointments a
  JOIN services s ON s.service_id = a.service_id
  JOIN queue_categories qc ON qc.category_id = a.category_id
  LEFT JOIN queue q ON q.appointment_id = a.appointment_id
  LEFT JOIN service_counters sc ON sc.counter_id = q.counter_id
  WHERE a.user_id = ?
    AND LOWER(a.status) NOT IN ('completed', 'cancelled', 'no_show')
    AND (
      a.appointment_date > ?
      OR (a.appointment_date = ? AND a.appointment_time >= ?)
    )
  ORDER BY a.appointment_date DESC, a.created_at DESC
");
$upStmt->bind_param("isss", $user_id, $today, $today, $nowTime);
$upStmt->execute();
$upcoming = $upStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Base query for history
$sql = "
  SELECT
    a.appointment_id,
    a.reference_code,
    a.appointment_date,
    a.appointment_time,
    a.arrival_time,
    a.status AS appt_status,
    a.source,
    s.service_name,
    qc.category_name,
    qc.prefix,
    q.queue_code,
    q.queue_number,
    q.status AS queue_status,
    sc.counter_name
  FROM appointments a
  JOIN services s ON s.service_id = a.service_id
  JOIN queue_categories qc ON qc.category_id = a.category_id
  LEFT JOIN queue q ON q.appointment_id = a.appointment_id
  LEFT JOIN service_counters sc ON sc.counter_id = q.counter_id
  WHERE a.user_id = ?
    AND (
      LOWER(a.status) IN ('completed', 'cancelled', 'no_show')
      OR (
        a.appointment_date < ?
        OR (a.appointment_date = ? AND a.appointment_time < ?)
      )
    )
";

$types = "isss";
$params = [$user_id, $today, $today, $nowTime];

// Filter by history status
if ($hist_status !== 'all') {
  $allowed = ['completed', 'cancelled', 'no_show', 'checked_in', 'booked'];

  if (in_array($hist_status, $allowed, true)) {
    $sql .= " AND LOWER(a.status) = ? ";
    $types .= "s";
    $params[] = $hist_status;
  } else {
    $hist_status = 'all';
  }
}

// Filter by from date
if ($from !== '') {
  $sql .= " AND a.appointment_date >= ? ";
  $types .= "s";
  $params[] = $from;
}

// Filter by to date
if ($to !== '') {
  $sql .= " AND a.appointment_date <= ? ";
  $types .= "s";
  $params[] = $to;
}

// Sort history
$sql .= " ORDER BY a.appointment_date DESC, a.created_at DESC";

// Run history query
$histStmt = $conn->prepare($sql);
$histStmt->bind_param($types, ...$params);
$histStmt->execute();
$history = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Appointment status badge
function status_badge(string $status): array
{
  $status = strtolower($status);

  if ($status === 'booked') return ['b-def', 'Booked'];
  if ($status === 'checked_in') return ['b-blue', 'Checked-in'];
  if ($status === 'completed') return ['b-grn', 'Completed'];
  if ($status === 'cancelled') return ['b-red', 'Cancelled'];
  if ($status === 'no_show') return ['b-amb', 'No-show'];

  return ['b-def', ucfirst($status)];
}

// Queue status badge
function queue_badge(?string $status): array
{
  if (!$status) return ['b-def', 'No queue yet'];

  $status = strtolower($status);

  if ($status === 'waiting') return ['b-amb', 'Waiting'];
  if ($status === 'serving') return ['b-blue', 'Serving'];
  if ($status === 'done') return ['b-grn', 'Done'];
  if ($status === 'cancelled') return ['b-red', 'Cancelled'];

  return ['b-def', ucfirst($status)];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Basic page setup -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Appointments — PhilHealth</title>

  <!-- Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">

  <!-- Page styles -->
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

    /* Background grid */
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

    /* Top header */
    header {
      position: sticky;
      top: 0;
      z-index: 50;
      background: rgba(255, 255, 255, 0.20);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.40);
    }

    .hdr {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 1.5rem;
      height: 62px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }

    .hdr-titles .sub {
      font-size: 11px;
      font-weight: 700;
      color: var(--g-dark);
      text-transform: uppercase;
      letter-spacing: .08em;
      opacity: .65;
    }

    .hdr-titles .main {
      font-weight: 800;
      font-size: 14px;
      color: var(--g-dark);
    }

    .hdr-btns {
      display: flex;
      align-items: center;
      gap: .5rem;
    }

    .btn-hdr {
      display: inline-flex;
      align-items: center;
      padding: 7px 14px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      transition: background .15s;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .btn-wht {
      background: rgba(255, 255, 255, 0.55);
      border: 1.5px solid rgba(255, 255, 255, 0.7);
      color: var(--g-dark);
    }

    .btn-wht:hover {
      background: rgba(255, 255, 255, 0.75);
    }

    .btn-grn {
      background: var(--g-dark);
      border: none;
      color: white;
      box-shadow: 0 2px 8px rgba(20, 60, 20, 0.22);
    }

    .btn-grn:hover {
      background: var(--g-mid);
    }

    /* Main content */
    main {
      max-width: 1100px;
      margin: 0 auto;
      padding: 2rem 1.5rem 4rem;
    }

    .card {
      background: var(--card);
      border-radius: 20px;
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      backdrop-filter: blur(8px);
    }

    .intro {
      padding: 1.75rem;
      margin-bottom: 1.5rem;
    }

    .intro h1 {
      font-size: clamp(1.4rem, 2.5vw, 1.9rem);
      font-weight: 800;
      color: var(--t-dark);
      letter-spacing: -.02em;
    }

    .intro p {
      margin-top: .5rem;
      font-size: 13.5px;
      color: var(--t-soft);
    }

    /* Tabs */
    .tabs-card {
      overflow: hidden;
    }

    .tab-bar {
      display: grid;
      grid-template-columns: 1fr 1fr;
      border-bottom: 2px solid #e8f5e8;
    }

    .tab-btn {
      padding: 1rem;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px;
      font-weight: 800;
      cursor: pointer;
      border: none;
      background: transparent;
      color: var(--t-muted);
      transition: background .15s, color .15s;
      position: relative;
    }

    .tab-btn.active {
      color: var(--g-dark);
      background: #f0f9f0;
    }

    .tab-btn.active::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      right: 0;
      height: 2.5px;
      background: var(--g-dark);
      border-radius: 2px 2px 0 0;
    }

    .tab-btn:hover:not(.active) {
      background: #f5fbf5;
      color: var(--t-mid);
    }

    .tab-panel {
      display: none;
      padding: 1.5rem;
    }

    .tab-panel.active {
      display: block;
    }

    /* Empty state */
    .empty-box {
      border: 1px solid #d4eed4;
      border-radius: 14px;
      padding: 3rem 2rem;
      text-align: center;
      background: #f5fbf5;
    }

    .empty-box .msg {
      font-weight: 600;
      color: var(--t-soft);
    }

    .empty-box a {
      display: inline-block;
      margin-top: 1rem;
      font-weight: 800;
      color: var(--g-mid);
      text-decoration: none;
      font-size: 14px;
    }

    .empty-box a:hover {
      text-decoration: underline;
    }

    /* Badge */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 3px 10px;
      border-radius: 999px;
      font-size: 11.5px;
      font-weight: 700;
      border: 1px solid transparent;
    }

    .b-def {
      background: #f0f7f0;
      color: var(--t-soft);
      border-color: #d0e8d0;
    }

    .b-blue {
      background: #dbeafe;
      color: #1e40af;
      border-color: #bfdbfe;
    }

    .b-grn {
      background: #dcfce7;
      color: #166534;
      border-color: #bbf7d0;
    }

    .b-red {
      background: #fee2e2;
      color: #991b1b;
      border-color: #fecaca;
    }

    .b-amb {
      background: #fef3c7;
      color: #92400e;
      border-color: #fde68a;
    }

    /* Mobile cards */
    .mobile-cards {
      display: grid;
      grid-template-columns: 1fr;
      gap: .75rem;
    }

    @media(min-width:768px) {
      .mobile-cards {
        display: none;
      }
    }

    .appt-card {
      background: #f5fbf5;
      border: 1.5px solid #d4eed4;
      border-radius: 16px;
      padding: 1rem;
    }

    .appt-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: .75rem;
    }

    .appt-date {
      font-weight: 800;
      font-size: 14px;
      color: var(--t-dark);
    }

    .appt-time {
      color: var(--t-muted);
      font-weight: 500;
      font-size: 13px;
    }

    .appt-svc {
      margin-top: 4px;
      font-size: 13px;
      color: var(--t-mid);
    }

    .appt-cat {
      font-size: 11.5px;
      color: var(--t-muted);
    }

    .appt-mid {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 6px;
      margin-top: .75rem;
    }

    .q-code {
      font-weight: 800;
      color: var(--g-dark);
      font-size: 14px;
    }

    .ctr-txt {
      font-size: 11.5px;
      color: var(--t-muted);
    }

    .appt-ref {
      margin-top: .75rem;
      font-size: 11.5px;
      color: var(--t-muted);
    }

    .ref-code {
      font-family: 'Courier New', monospace;
      font-size: 11px;
      background: #e8f5e8;
      border: 1px solid #cce8cc;
      border-radius: 6px;
      padding: 2px 8px;
      color: var(--g-dark);
    }

    /* Desktop table */
    .desktop-table {
      display: none;
      overflow-x: auto;
    }

    @media(min-width:768px) {
      .desktop-table {
        display: block;
      }
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead th {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: var(--t-muted);
      font-weight: 700;
      padding: 10px 12px;
      border-bottom: 2px solid #e8f5e8;
      text-align: left;
      white-space: nowrap;
    }

    tbody td {
      padding: 11px 12px;
      border-bottom: 1px solid #f0f9f0;
      font-size: 13.5px;
      color: var(--t-mid);
    }

    tbody tr:last-child td {
      border-bottom: none;
    }

    tbody tr:hover td {
      background: #f7fdf7;
    }

    .td-main {
      font-weight: 700;
      font-size: 13.5px;
      color: var(--t-dark);
    }

    .td-sub {
      font-size: 11px;
      color: var(--t-muted);
      margin-top: 2px;
    }

    .reminder {
      margin-top: 1rem;
      padding: .875rem 1rem;
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 12px;
      font-size: 13px;
      color: #166534;
    }

    /* Filter box */
    .filter-box {
      background: #f0f9f0;
      border: 1.5px solid #d4eed4;
      border-radius: 16px;
      padding: 1rem;
      margin-bottom: 1rem;
    }

    .filter-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: .75rem;
    }

    @media(min-width:640px) {
      .filter-grid {
        grid-template-columns: repeat(4, 1fr);
        align-items: end;
      }
    }

    .f-lbl {
      display: block;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: var(--t-soft);
      margin-bottom: 6px;
    }

    .f-sel,
    .f-date {
      width: 100%;
      background: white;
      border: 1.5px solid #cce8cc;
      border-radius: 10px;
      padding: 9px 12px;
      font-size: 13px;
      font-weight: 500;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--t-dark);
      outline: none;
      transition: border-color .15s;
      appearance: none;
      -webkit-appearance: none;
    }

    .f-sel:focus,
    .f-date:focus {
      border-color: var(--g-main);
    }

    .f-sel option {
      background: white;
    }

    .f-btns {
      display: flex;
      gap: .5rem;
    }

    .btn-apply {
      flex: 1;
      background: var(--g-dark);
      border: none;
      color: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 700;
      font-size: 13px;
      padding: 9px;
      border-radius: 10px;
      cursor: pointer;
      transition: background .15s;
    }

    .btn-apply:hover {
      background: var(--g-mid);
    }

    .btn-reset {
      flex: 1;
      background: white;
      border: 1.5px solid #cce8cc;
      color: var(--t-mid);
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 700;
      font-size: 13px;
      padding: 9px;
      border-radius: 10px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: background .15s;
    }

    .btn-reset:hover {
      background: #f0f9f0;
    }
  </style>
</head>

<body>
  <!-- Top header -->
  <header>
    <div class="hdr">
      <div class="hdr-titles">
        <div class="sub">My Appointments</div>
        <div class="main">Bookings &amp; Queue Status</div>
      </div>

      <div class="hdr-btns">
        <a href="dashboard.php" class="btn-hdr btn-wht">Dashboard</a>

        <a href="book_appointment.php" class="btn-hdr btn-grn inline-flex items-center justify-center gap-2">
          <i data-lucide="plus" class="w-5 h-5 shrink-0"></i>
          <span>Book</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main page -->
  <main>
    <!-- Intro -->
    <div class="card intro">
      <h1>My Appointments</h1>
      <p>Booked appointments have no queue number until you check in at the office.</p>
    </div>

    <!-- Tabs section -->
    <div class="card tabs-card">
      <div class="tab-bar">
        <button type="button" id="btn-upcoming" class="tab-btn" onclick="openTab('upcoming')">
          Upcoming (<?php echo count($upcoming); ?>)
        </button>
        <button type="button" id="btn-history" class="tab-btn" onclick="openTab('history')">
          History (<?php echo count($history); ?>)
        </button>
      </div>

      <!-- Upcoming tab -->
      <div id="tab-upcoming" class="tab-panel">
        <?php if (!count($upcoming)): ?>
          <div class="empty-box">
            <div class="msg">No upcoming appointments.</div>
            <a href="book_appointment.php">Book one now →</a>
          </div>
        <?php else: ?>

          <!-- Mobile cards -->
          <div class="mobile-cards">
            <?php foreach ($upcoming as $r):
              [$abg, $albl] = status_badge($r['appt_status']);
              [$qbg, $qlbl] = queue_badge($r['queue_status'] ?? null);
              $dl = date('M d, Y', strtotime($r['appointment_date']));
              $tl = $r['appointment_time'] ? date('h:i A', strtotime($r['appointment_time'])) : '—';
            ?>
              <div class="appt-card">
                <div class="appt-top">
                  <div>
                    <div class="appt-date"><?php echo e($dl); ?> <span class="appt-time">· <?php echo e($tl); ?></span></div>
                    <div class="appt-svc"><?php echo e($r['service_name']); ?></div>
                    <div class="appt-cat"><?php echo e($r['category_name']); ?> (<?php echo e($r['prefix']); ?>)</div>
                  </div>
                  <span class="badge <?php echo $abg; ?>"><?php echo e($albl); ?></span>
                </div>

                <div class="appt-mid">
                  <span class="badge <?php echo $qbg; ?>"><?php echo e($qlbl); ?></span>
                  <?php if ($r['queue_code']): ?>
                    <span class="q-code"><?php echo e($r['queue_code']); ?></span>
                  <?php endif; ?>
                  <span class="ctr-txt">Counter: <?php echo e($r['counter_name'] ?? '—'); ?></span>
                </div>

                <div class="appt-ref">
                  Ref: <span class="ref-code"><?php echo e($r['reference_code']); ?></span>
                </div>

                <?php if ($r['arrival_time']): ?>
                  <div style="margin-top:6px;font-size:11px;color:var(--t-muted);">
                    Arrived: <?php echo date('h:i A', strtotime($r['arrival_time'])); ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Desktop table -->
          <div class="desktop-table">
            <table>
              <thead>
                <tr>
                  <th>Date / Time</th>
                  <th>Service</th>
                  <th>Reference</th>
                  <th>Appointment</th>
                  <th>Queue</th>
                  <th>Counter</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($upcoming as $r):
                  [$abg, $albl] = status_badge($r['appt_status']);
                  [$qbg, $qlbl] = queue_badge($r['queue_status'] ?? null);
                  $dl = date('M d, Y', strtotime($r['appointment_date']));
                  $tl = $r['appointment_time'] ? date('h:i A', strtotime($r['appointment_time'])) : '—';
                ?>
                  <tr>
                    <td>
                      <div class="td-main"><?php echo e($dl); ?></div>
                      <div class="td-sub"><?php echo e($tl); ?></div>
                      <?php if ($r['arrival_time']): ?>
                        <div class="td-sub" style="margin-top:4px;">
                          Arrived: <?php echo date('h:i A', strtotime($r['arrival_time'])); ?>
                        </div>
                      <?php endif; ?>
                    </td>

                    <td>
                      <div class="td-main"><?php echo e($r['service_name']); ?></div>
                      <div class="td-sub"><?php echo e($r['category_name']); ?> (<?php echo e($r['prefix']); ?>)</div>
                    </td>

                    <td>
                      <span class="ref-code"><?php echo e($r['reference_code']); ?></span>
                      <div class="td-sub" style="margin-top:4px;">Use at kiosk to check in</div>
                    </td>

                    <td>
                      <span class="badge <?php echo $abg; ?>"><?php echo e($albl); ?></span>
                    </td>

                    <td>
                      <?php if ($r['queue_code']): ?>
                        <div class="q-code" style="margin-bottom:4px;"><?php echo e($r['queue_code']); ?></div>
                      <?php endif; ?>
                      <span class="badge <?php echo $qbg; ?>"><?php echo e($qlbl); ?></span>
                    </td>

                    <td><?php echo e($r['counter_name'] ?? '—'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="reminder">
            <strong>Reminder:</strong> Your queue number will only be assigned after check-in at the office kiosk/staff terminal.
          </div>
        <?php endif; ?>
      </div>

      <!-- History tab -->
      <div id="tab-history" class="tab-panel">
        <!-- Filter form -->
        <div class="filter-box">
          <form method="GET">
            <div class="filter-grid">
              <div>
                <label class="f-lbl">Status</label>
                <select name="hist_status" class="f-sel">
                  <?php
                  $opts = [
                    'all' => 'All',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    'no_show' => 'No-show',
                    'checked_in' => 'Checked-in',
                    'booked' => 'Booked'
                  ];
                  foreach ($opts as $k => $v):
                  ?>
                    <option value="<?php echo e($k); ?>" <?php echo ($hist_status === $k) ? 'selected' : ''; ?>>
                      <?php echo e($v); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="f-lbl">From</label>
                <input type="date" name="from" value="<?php echo e($from); ?>" class="f-date">
              </div>

              <div>
                <label class="f-lbl">To</label>
                <input type="date" name="to" value="<?php echo e($to); ?>" class="f-date">
              </div>

              <div>
                <label class="f-lbl" style="visibility:hidden;">Apply</label>
                <div class="f-btns">
                  <button type="submit" class="btn-apply">Apply</button>
                  <a href="my_appointments.php" class="btn-reset">Reset</a>
                </div>
              </div>
            </div>
          </form>
        </div>

        <?php if (!count($history)): ?>
          <div class="empty-box">
            <div class="msg">No appointment history matches your filter.</div>
          </div>
        <?php else: ?>

          <!-- Mobile cards -->
          <div class="mobile-cards">
            <?php foreach ($history as $r):
              [$abg, $albl] = status_badge($r['appt_status']);
              [$qbg, $qlbl] = queue_badge($r['queue_status'] ?? null);
              $dl = date('M d, Y', strtotime($r['appointment_date']));
              $tl = $r['appointment_time'] ? date('h:i A', strtotime($r['appointment_time'])) : '—';
            ?>
              <div class="appt-card">
                <div class="appt-top">
                  <div>
                    <div class="appt-date"><?php echo e($dl); ?> <span class="appt-time">· <?php echo e($tl); ?></span></div>
                    <div class="appt-svc"><?php echo e($r['service_name']); ?></div>
                    <div class="appt-cat"><?php echo e($r['category_name']); ?> (<?php echo e($r['prefix']); ?>)</div>
                  </div>
                  <span class="badge <?php echo $abg; ?>"><?php echo e($albl); ?></span>
                </div>

                <div class="appt-mid">
                  <span class="badge <?php echo $qbg; ?>"><?php echo e($qlbl); ?></span>
                  <?php if ($r['queue_code']): ?>
                    <span class="q-code"><?php echo e($r['queue_code']); ?></span>
                  <?php endif; ?>
                  <span class="ctr-txt">Counter: <?php echo e($r['counter_name'] ?? '—'); ?></span>
                </div>

                <div class="appt-ref">
                  Ref: <span class="ref-code"><?php echo e($r['reference_code']); ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Desktop table -->
          <div class="desktop-table">
            <table>
              <thead>
                <tr>
                  <th>Date / Time</th>
                  <th>Service</th>
                  <th>Reference</th>
                  <th>Appointment</th>
                  <th>Queue</th>
                  <th>Counter</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $r):
                  [$abg, $albl] = status_badge($r['appt_status']);
                  [$qbg, $qlbl] = queue_badge($r['queue_status'] ?? null);
                  $dl = date('M d, Y', strtotime($r['appointment_date']));
                  $tl = $r['appointment_time'] ? date('h:i A', strtotime($r['appointment_time'])) : '—';
                ?>
                  <tr>
                    <td>
                      <div class="td-main"><?php echo e($dl); ?></div>
                      <div class="td-sub"><?php echo e($tl); ?></div>
                    </td>

                    <td>
                      <div class="td-main"><?php echo e($r['service_name']); ?></div>
                      <div class="td-sub"><?php echo e($r['category_name']); ?> (<?php echo e($r['prefix']); ?>)</div>
                    </td>

                    <td>
                      <span class="ref-code"><?php echo e($r['reference_code']); ?></span>
                    </td>

                    <td>
                      <span class="badge <?php echo $abg; ?>"><?php echo e($albl); ?></span>
                    </td>

                    <td>
                      <?php if ($r['queue_code']): ?>
                        <div class="q-code" style="margin-bottom:4px;"><?php echo e($r['queue_code']); ?></div>
                      <?php endif; ?>
                      <span class="badge <?php echo $qbg; ?>"><?php echo e($qlbl); ?></span>
                    </td>

                    <td><?php echo e($r['counter_name'] ?? '—'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Tab script -->
  <script>
    function openTab(name) {
      ['upcoming', 'history'].forEach(id => {
        document.getElementById('tab-' + id).classList.toggle('active', id === name);
        document.getElementById('btn-' + id).classList.toggle('active', id === name);
      });
    }

    (function() {
      const params = new URLSearchParams(window.location.search);
      openTab(params.has('hist_status') || params.has('from') || params.has('to') ? 'history' : 'upcoming');
    })();
  </script>

  <!-- Lucide icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    lucide.createIcons();
  </script>
</body>

</html>