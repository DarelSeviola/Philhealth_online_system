<?php
// Start staff session
session_name('staff_session');
session_start();

// Load needed files
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/helpers.php";

// Set local time
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// Allow only staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
  header("Location: login.php");
  exit();
}

// Fixed IDs
const PRIORITY_COUNTER = 2;
const MEMBERSHIP_CAT_ID = 1;
const BENEFIT_AVAILMENT_CAT_ID = 2;
const ONLINE_GRACE_MINUTES = 15;

// Counter groups
const MEMBERSHIP_COUNTERS = [4, 6, 7];
const BENEFIT_AVAILMENT_COUNTERS = [8, 9];

/* =========================================================
   Counter status from session
========================================================= */

$activeMap = [
  'priority' => $_SESSION['active_counters']['priority'] ?? [],
  'membership' => $_SESSION['active_counters']['membership'] ?? [],
  'hospitalization' => $_SESSION['active_counters']['hospitalization'] ?? [],
];

$flatActiveCounters = [];
foreach ($activeMap as $groupCounters) {
  if (is_array($groupCounters)) {
    foreach ($groupCounters as $cid) {
      $flatActiveCounters[(int)$cid] = true;
    }
  }
}

/* =========================================================
   Simple database helper functions
========================================================= */

function fetch_one(mysqli $conn, string $sql, string $types, array $params): ?array
{
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return null;
  }

  if ($types !== '') {
    $stmt->bind_param($types, ...$params);
  }

  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $row ?: null;
}

function fetch_all(mysqli $conn, string $sql, string $types, array $params): array
{
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return [];
  }

  if ($types !== '') {
    $stmt->bind_param($types, ...$params);
  }

  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  return $rows ?: [];
}

/* =========================================================
   Grace / validity logic
========================================================= */

function board_appointment_validity_sql(string $queueAlias = 'q', string $apptAlias = 'a'): string
{
  $mins = (int)ONLINE_GRACE_MINUTES;

  return "
    (
      {$queueAlias}.queue_type <> 'appointment'
      OR {$queueAlias}.checked_in_at IS NOT NULL
      OR NOW() <= DATE_ADD(TIMESTAMP({$queueAlias}.queue_date, {$apptAlias}.appointment_time), INTERVAL {$mins} MINUTE)
    )
  ";
}

function board_priority_validity_sql(string $queueAlias = 'q', string $apptAlias = 'a'): string
{
  $mins = (int)ONLINE_GRACE_MINUTES;

  return "
    (
      {$queueAlias}.checked_in_at IS NOT NULL
      OR (
        {$apptAlias}.source = 'online'
        AND NOW() <= DATE_ADD(TIMESTAMP({$queueAlias}.queue_date, {$apptAlias}.appointment_time), INTERVAL {$mins} MINUTE)
      )
    )
  ";
}

/* =========================================================
   Queue data functions
   FINAL LOGIC:
   - online before walk-in
   - lower queue_number first
   - skip overdue online reservations automatically
========================================================= */

function serving_list_by_counters(
  mysqli $conn,
  string $date,
  array $counters,
  ?int $category_id = null,
  bool $priorityOnly = false
): array {
  if (!$counters) {
    return [];
  }

  $placeholders = implode(',', array_fill(0, count($counters), '?'));
  $types = "s" . str_repeat("i", count($counters));
  $params = array_merge([$date], array_map('intval', $counters));

  $catSql = "";
  if ($category_id !== null) {
    $catSql = " AND q.category_id = ? ";
    $types .= "i";
    $params[] = $category_id;
  }

  $extraWhere = "";
  $orderSql = "";

  if ($priorityOnly) {
    $extraWhere = "
      AND q.queue_type = 'priority'
      AND q.verification_status = 'approved'
      AND " . board_priority_validity_sql('q', 'a') . "
    ";
    $orderSql = "
      ORDER BY
        CASE
          WHEN a.source = 'online' THEN 1
          WHEN a.source = 'walkin' THEN 2
          ELSE 3
        END,
        q.queue_number ASC,
        q.created_at ASC
    ";
  } else {
    $extraWhere = "
      AND q.queue_type IN ('appointment','walkin')
      AND " . board_appointment_validity_sql('q', 'a') . "
    ";
    $orderSql = "
      ORDER BY
        CASE
          WHEN q.queue_type = 'appointment' THEN 1
          WHEN q.queue_type = 'walkin' THEN 2
          ELSE 3
        END,
        q.queue_number ASC,
        q.created_at ASC
    ";
  }

  $sql = "
    SELECT
      q.queue_code,
      q.queue_number,
      q.counter_id,
      q.queue_type,
      a.source,
      a.appointment_time,
      COALESCE(s.service_name, '—') AS service_name
    FROM queue q
    LEFT JOIN appointments a ON a.appointment_id = q.appointment_id
    LEFT JOIN services s ON s.service_id = a.service_id
    WHERE q.queue_date = ?
      AND q.status = 'serving'
      AND q.counter_id IN ($placeholders)
      $catSql
      $extraWhere
    $orderSql
  ";

  return fetch_all($conn, $sql, $types, $params);
}

function next_waiting_overall(
  mysqli $conn,
  string $date,
  ?int $category_id = null,
  bool $priorityOnly = false
): ?array {
  $types = "s";
  $params = [$date];
  $where = "WHERE q.queue_date = ? AND q.status = 'waiting'";

  if ($category_id !== null) {
    $where .= " AND q.category_id = ?";
    $types .= "i";
    $params[] = $category_id;
  }

  if ($priorityOnly) {
    $where .= "
      AND q.queue_type = 'priority'
      AND q.verification_status = 'approved'
      AND " . board_priority_validity_sql('q', 'a') . "
    ";
    $orderSql = "
      ORDER BY
        CASE
          WHEN a.source = 'online' THEN 1
          WHEN a.source = 'walkin' THEN 2
          ELSE 3
        END,
        q.queue_number ASC,
        q.created_at ASC
    ";
  } else {
    $where .= "
      AND q.queue_type IN ('appointment','walkin')
      AND " . board_appointment_validity_sql('q', 'a') . "
    ";
    $orderSql = "
      ORDER BY
        CASE
          WHEN q.queue_type = 'appointment' THEN 1
          WHEN q.queue_type = 'walkin' THEN 2
          ELSE 3
        END,
        q.queue_number ASC,
        q.created_at ASC
    ";
  }

  $sql = "
    SELECT
      q.queue_code,
      q.queue_number,
      q.counter_id,
      q.queue_type,
      a.source,
      a.appointment_time,
      COALESCE(s.service_name, '—') AS service_name
    FROM queue q
    LEFT JOIN appointments a ON a.appointment_id = q.appointment_id
    LEFT JOIN services s ON s.service_id = a.service_id
    $where
    $orderSql
    LIMIT 1
  ";

  return fetch_one($conn, $sql, $types, $params);
}

function payload(mysqli $conn, string $today): array
{
  $m_serving_list = serving_list_by_counters(
    $conn,
    $today,
    MEMBERSHIP_COUNTERS,
    MEMBERSHIP_CAT_ID,
    false
  );
  $m_serving_main = $m_serving_list ? $m_serving_list[0] : null;
  $m_next = next_waiting_overall(
    $conn,
    $today,
    MEMBERSHIP_CAT_ID,
    false
  );

  $h_serving_list = serving_list_by_counters(
    $conn,
    $today,
    BENEFIT_AVAILMENT_COUNTERS,
    BENEFIT_AVAILMENT_CAT_ID,
    false
  );
  $h_serving_main = $h_serving_list ? $h_serving_list[0] : null;
  $h_next = next_waiting_overall(
    $conn,
    $today,
    BENEFIT_AVAILMENT_CAT_ID,
    false
  );

  $p_serving_list = serving_list_by_counters(
    $conn,
    $today,
    [PRIORITY_COUNTER],
    null,
    true
  );
  $p_serving_main = $p_serving_list ? $p_serving_list[0] : null;
  $p_next = next_waiting_overall(
    $conn,
    $today,
    null,
    true
  );

  return [
    'server_time' => date('Y-m-d H:i:s'),
    'today_label' => date('l, M d, Y'),
    'clock' => date('h:i:s A'),

    'membership' => [
      'serving_main' => $m_serving_main,
      'serving_list' => $m_serving_list,
      'next' => $m_next,
    ],

    'hospitalization' => [
      'serving_main' => $h_serving_main,
      'serving_list' => $h_serving_list,
      'next' => $h_next,
    ],

    'priority' => [
      'serving_main' => $p_serving_main,
      'serving_list' => $p_serving_list,
      'next' => $p_next,
    ],
  ];
}

/* =========================================================
   Ajax refresh
========================================================= */

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(payload($conn, $today), JSON_UNESCAPED_UNICODE);
  exit();
}

$init = payload($conn, $today);

function code_or_dash(?array $row): string
{
  return ($row && !empty($row['queue_code'])) ? (string)$row['queue_code'] : '—';
}

function service_or_dash(?array $row): string
{
  return ($row && !empty($row['service_name'])) ? (string)$row['service_name'] : '—';
}

function serving_for_counter(array $rows, int $counterId): ?array
{
  foreach ($rows as $row) {
    if ((int)($row['counter_id'] ?? 0) === $counterId) {
      return $row;
    }
  }
  return null;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover" />
  <title>PhilHealth Queue Board</title>
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
    :root {
      --card: rgba(255, 255, 255, .78);
      --bdr: rgba(255, 255, 255, .55);
    }

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
    }

    .tile {
      background: var(--card);
      border: 1px solid var(--bdr);
      backdrop-filter: blur(18px);
      box-shadow: 0 12px 50px rgba(10, 50, 10, .18);
    }

    .soft {
      background: rgba(255, 255, 255, .70);
      border: 1px solid rgba(255, 255, 255, .55);
    }

    .ticker {
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, .55);
      background: rgba(255, 255, 255, .55);
      backdrop-filter: blur(14px);
    }

    .ticker-track {
      display: inline-flex;
      gap: 2.5rem;
      white-space: nowrap;
      will-change: transform;
      animation: scroll 20s linear infinite;
      padding: .45rem 0;
    }

    @keyframes scroll {
      from {
        transform: translateX(0);
      }

      to {
        transform: translateX(-50%);
      }
    }

    .flash {
      animation: flash 900ms ease-out 1;
    }

    @keyframes flash {
      0% {
        transform: scale(1);
        box-shadow: 0 0 0 rgba(0, 0, 0, 0);
      }

      20% {
        transform: scale(1.02);
        box-shadow: 0 12px 40px rgba(20, 80, 20, .22);
      }

      100% {
        transform: scale(1);
        box-shadow: 0 0 0 rgba(0, 0, 0, 0);
      }
    }

    .fs {
      position: fixed;
      right: 16px;
      bottom: 16px;
      z-index: 50;
      display: flex;
      flex-direction: column;
      gap: .5rem;
    }

    .qcode-serving {
      font-size: clamp(1.8rem, 3.3vw, 3.2rem);
      line-height: 1.03;
    }

    .qcode-next {
      font-size: clamp(1.35rem, 2.7vw, 2.55rem);
      line-height: 1.08;
    }

    @media (prefers-reduced-motion: reduce) {
      .ticker-track {
        animation: none;
      }

      .flash {
        animation: none;
      }
    }
  </style>
</head>

<body class="min-h-dvh text-slate-900 overflow-x-hidden">
  <div class="fixed inset-0 -z-10 lime-bg"></div>
  <div class="fixed inset-0 -z-10 grid-overlay pointer-events-none"></div>

  <div class="mx-auto w-full max-w-[1700px] px-3 sm:px-5 lg:px-7 py-4 sm:py-5 space-y-3 sm:space-y-4">

    <header class="tile rounded-3xl px-4 sm:px-5 py-3 sm:py-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <img
          src="../logo/philhealth_Logo.png"
          alt="PhilHealth Logo"
          class="h-10 w-10 sm:h-12 sm:w-12 object-contain rounded-2xl bg-white/70 border border-white/60 p-1" />
        <div class="min-w-0">
          <div class="text-base sm:text-lg lg:text-xl font-extrabold tracking-tight truncate">
            PhilHealth Queue Board
          </div>
          <div class="text-xs sm:text-sm text-slate-700/80">
            Please prepare MDR and valid ID
          </div>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 lg:justify-end">
        <div class="text-left sm:text-right">
          <div id="clock" class="text-xl sm:text-2xl font-black tracking-tight">
            <?php echo e($init['clock']); ?>
          </div>
          <div id="todayLbl" class="text-xs sm:text-sm text-slate-700/80">
            <?php echo e($init['today_label']); ?>
          </div>
        </div>

        <a
          href="serve.php"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-brand-700 text-white font-extrabold shadow-lg hover:opacity-95 active:scale-[0.99] transition w-full sm:w-auto text-sm">
          Go to Staff Serve Panel →
        </a>
      </div>
    </header>

    <div class="ticker rounded-2xl px-4 sm:px-5">
      <div class="ticker-track" aria-hidden="true">
        <div class="font-extrabold text-slate-800">NOTICE:</div>
        <div class="font-semibold text-slate-700">Special clients (Senior Citizen / PWD / Pregnant) proceed to Counter 2 (Ground Floor).</div>
        <div class="font-semibold text-slate-700">Please maintain order and wait for your queue to be called.</div>

        <div class="font-extrabold text-slate-800">NOTICE:</div>
        <div class="font-semibold text-slate-700">Special clients (Senior Citizen / PWD / Pregnant) proceed to Counter 2 (Ground Floor).</div>
        <div class="font-semibold text-slate-700">Please maintain order and wait for your queue to be called.</div>
      </div>
    </div>

    <main class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-4">

      <section id="membershipSection" class="tile rounded-3xl p-4 sm:p-5 lg:order-1">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-base sm:text-lg font-extrabold">
              Membership <span class="text-slate-500">(M)</span>
            </div>
            <div class="text-xs text-slate-600 mt-1">Shared Membership Queue</div>
          </div>
          <span class="px-3 py-1 rounded-full bg-brand-700 text-white text-xs font-extrabold">LIVE</span>
        </div>

        <div class="grid grid-cols-3 gap-2 mt-4">
          <?php foreach (MEMBERSHIP_COUNTERS as $cid): ?>
            <?php
            $serving = serving_for_counter($init['membership']['serving_list'], $cid);
            $isActive = isset($flatActiveCounters[$cid]);
            ?>
            <div class="soft rounded-2xl p-4 text-center">
              <div class="font-bold text-sm mb-1">Counter <?php echo $cid; ?></div>
              <div class="text-xs font-bold mb-2 <?php echo $isActive ? 'text-emerald-700' : 'text-slate-400'; ?>">
                ● <?php echo $isActive ? 'Active' : 'Inactive'; ?>
              </div>
              <div class="text-xs font-bold text-gray-500">NOW SERVING</div>
              <div id="m-counter-<?php echo $cid; ?>" class="text-2xl font-black <?php echo $isActive ? 'text-green-700' : 'text-slate-300'; ?>">
                <?php echo e($serving['queue_code'] ?? '—'); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="mt-3 soft rounded-2xl p-4 text-center">
          <div class="text-xs font-bold text-gray-500 uppercase">Next in Shared Line</div>
          <div id="m-next-code" class="text-3xl font-black text-slate-900 mt-2">
            <?php echo e(code_or_dash($init['membership']['next'])); ?>
          </div>
          <div id="m-next-service" class="text-sm text-slate-600 mt-2">
            <?php echo e($init['membership']['next'] ? service_or_dash($init['membership']['next']) : 'No waiting queue'); ?>
          </div>
        </div>

        <div class="mt-3 soft rounded-2xl p-4 text-lg text-slate-900">
          <div class="font-extrabold text-slate-600 uppercase mb-2">Service Guide</div>
          <div>Counter 4 – All Membership Services</div>
          <div>Counter 6 – All Membership Services</div>
          <div>Counter 7 – All Membership Services</div>
        </div>
      </section>

      <section id="prioritySection" class="tile rounded-3xl p-4 sm:p-5 text-center lg:order-2">
        <div class="flex items-center justify-between gap-3">
          <div class="text-base sm:text-lg font-extrabold">
            Priority <span class="text-slate-500">(Counter 2)</span>
          </div>
          <span class="px-3 py-1 rounded-full bg-red-600 text-white text-xs font-extrabold animate-pulse">LIVE</span>
        </div>

        <div class="mt-3 flex flex-col items-center justify-center">
          <div class="soft w-full rounded-2xl py-4 sm:py-5 px-4 sm:px-5 text-center">
            <div class="text-sm sm:text-base font-black tracking-wide text-slate-800">SPECIAL CLIENTS</div>
            <div class="mt-2 font-black leading-tight text-brand-700" style="font-size: clamp(0.9rem, 2.1vw, 2.1rem);">
              PROCEED TO GROUND FLOOR
            </div>
            <div class="text-[11px] sm:text-xs mt-2 font-semibold text-slate-700/80">
              Counter 2 Assistance Area
            </div>
          </div>
        </div>

        <?php $priorityActive = isset($flatActiveCounters[PRIORITY_COUNTER]); ?>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
          <div class="soft rounded-2xl p-4 flex flex-col">
            <div class="text-[11px] font-extrabold text-slate-500 uppercase">Now Serving</div>
            <div class="mt-1 text-xs font-bold <?php echo $priorityActive ? 'text-emerald-700' : 'text-slate-400'; ?>">
              ● <?php echo $priorityActive ? 'Active' : 'Inactive'; ?>
            </div>
            <div id="p-serving-code" class="mt-3 text-center font-black <?php echo $priorityActive ? 'text-brand-700' : 'text-slate-300'; ?> qcode-serving break-words">
              <?php echo e(code_or_dash($init['priority']['serving_main'])); ?>
            </div>
            <div id="p-serving-counter" class="mt-2 text-center text-sm font-extrabold text-slate-800">
              <?php echo e($init['priority']['serving_main'] ? 'Counter 2' : '—'); ?>
            </div>
            <div id="p-serving-service" class="mt-auto pt-2 text-center text-[11px] font-bold text-slate-600">
              <?php echo e($init['priority']['serving_main'] ? service_or_dash($init['priority']['serving_main']) : 'No serving queue'); ?>
            </div>
          </div>

          <div class="soft rounded-2xl p-4 flex flex-col">
            <div class="text-[11px] font-extrabold text-slate-500 uppercase">Next in Line</div>
            <div id="p-next-code" class="mt-3 text-center font-black text-slate-900 qcode-next break-words">
              <?php echo e(code_or_dash($init['priority']['next'])); ?>
            </div>
            <div id="p-next-counter" class="mt-2 text-center text-sm font-extrabold text-slate-800">
              <?php echo e($init['priority']['next'] ? 'Counter 2' : '—'); ?>
            </div>
            <div id="p-next-service" class="mt-auto pt-2 text-center text-[11px] font-bold text-slate-600">
              <?php echo e($init['priority']['next'] ? service_or_dash($init['priority']['next']) : 'No waiting queue'); ?>
            </div>
          </div>
        </div>
      </section>

      <section id="hospitalSection" class="tile rounded-3xl p-4 sm:p-5 lg:order-3">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-base sm:text-lg font-extrabold">
              Benefits Availment <span class="text-slate-500">(B)</span>
            </div>
            <div class="text-xs text-slate-600 mt-1">Shared Benefit Queue</div>
          </div>
          <span class="px-3 py-1 rounded-full bg-brand-700 text-white text-xs font-extrabold">LIVE</span>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-4">
          <?php foreach (BENEFIT_AVAILMENT_COUNTERS as $cid): ?>
            <?php
            $serving = serving_for_counter($init['hospitalization']['serving_list'], $cid);
            $isActive = isset($flatActiveCounters[$cid]);
            ?>
            <div class="soft rounded-2xl p-4 text-center">
              <div class="font-bold text-sm mb-1">Counter <?php echo $cid; ?></div>
              <div class="text-xs font-bold mb-2 <?php echo $isActive ? 'text-emerald-700' : 'text-slate-400'; ?>">
                ● <?php echo $isActive ? 'Active' : 'Inactive'; ?>
              </div>
              <div class="text-xs font-bold text-gray-500">NOW SERVING</div>
              <div id="h-counter-<?php echo $cid; ?>" class="text-2xl font-black <?php echo $isActive ? 'text-green-700' : 'text-slate-300'; ?>">
                <?php echo e($serving['queue_code'] ?? '—'); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="mt-3 soft rounded-2xl p-4 text-center">
          <div class="text-xs font-bold text-gray-500 uppercase">Next in Shared Line</div>
          <div id="h-next-code" class="text-3xl font-black text-slate-900 mt-2">
            <?php echo e(code_or_dash($init['hospitalization']['next'])); ?>
          </div>
          <div id="h-next-service" class="text-sm text-slate-600 mt-2">
            <?php echo e($init['hospitalization']['next'] ? service_or_dash($init['hospitalization']['next']) : 'No waiting queue'); ?>
          </div>
        </div>

        <div class="mt-3 soft rounded-2xl p-4 text-lg text-slate-900">
          <div class="font-extrabold text-slate-600 uppercase mb-2">Service Guide</div>
          <div>Counter 8 – All Benefit Services</div>
          <div>Counter 9 – All Benefit Services</div>
        </div>
      </section>
    </main>
  </div>

  <div class="fs">
    <button
      id="fsBtn"
      class="rounded-xl bg-white/80 border border-white/60 px-4 py-2 text-sm font-extrabold text-slate-800 hover:bg-white transition shadow-lg">
      Enter Fullscreen
    </button>

    <button
      id="soundBtn"
      class="rounded-xl bg-brand-700 px-4 py-2 text-sm font-extrabold text-white hover:opacity-95 transition shadow-lg">
      Enable Voice
    </button>
  </div>

  <script>
    function tickClock() {
      const now = new Date();
      const hh = String(((now.getHours() + 11) % 12) + 1).padStart(2, '0');
      const mm = String(now.getMinutes()).padStart(2, '0');
      const ss = String(now.getSeconds()).padStart(2, '0');
      const ampm = now.getHours() >= 12 ? 'PM' : 'AM';

      const el = document.getElementById('clock');
      if (el) {
        el.textContent = `${hh}:${mm}:${ss} ${ampm}`;
      }
    }

    setInterval(tickClock, 1000);
    tickClock();

    document.getElementById('fsBtn').addEventListener('click', async () => {
      try {
        if (!document.fullscreenElement) {
          await document.documentElement.requestFullscreen();
          document.getElementById('fsBtn').textContent = 'Exit Fullscreen';
        } else {
          await document.exitFullscreen();
          document.getElementById('fsBtn').textContent = 'Enter Fullscreen';
        }
      } catch (e) {}
    });

    let voiceEnabled = false;

    document.getElementById('soundBtn').addEventListener('click', () => {
      voiceEnabled = true;

      const btn = document.getElementById('soundBtn');
      btn.textContent = 'Voice Enabled';
      btn.disabled = true;

      try {
        const u = new SpeechSynthesisUtterance('Voice enabled.');
        u.rate = 1;
        speechSynthesis.speak(u);
      } catch (e) {}
    });

    function speak(text) {
      if (!voiceEnabled) return;

      try {
        speechSynthesis.cancel();
        const u = new SpeechSynthesisUtterance(text);
        u.rate = 1;
        speechSynthesis.speak(u);
      } catch (e) {}
    }

    const last = {
      M: null,
      H: null,
      P: null
    };

    function flashSection(id) {
      const el = document.getElementById(id);
      if (!el) return;

      el.classList.remove('flash');
      void el.offsetWidth;
      el.classList.add('flash');
    }

    function servingMap(list) {
      const map = {};
      (list || []).forEach(row => {
        if (row && row.counter_id != null) {
          map[String(row.counter_id)] = row;
        }
      });
      return map;
    }

    function updateBoard(data) {
      if (data.clock) {
        const clockEl = document.getElementById('clock');
        if (clockEl) clockEl.textContent = data.clock;
      }

      if (data.today_label) {
        const todayEl = document.getElementById('todayLbl');
        if (todayEl) todayEl.textContent = data.today_label;
      }

      const mMap = servingMap(data.membership?.serving_list || []);
      [4, 6, 7].forEach(cid => {
        const el = document.getElementById(`m-counter-${cid}`);
        if (el) {
          el.textContent = mMap[String(cid)]?.queue_code || '—';
        }
      });

      const mNextCode = document.getElementById('m-next-code');
      const mNextService = document.getElementById('m-next-service');
      if (mNextCode) mNextCode.textContent = data.membership?.next?.queue_code || '—';
      if (mNextService) mNextService.textContent = data.membership?.next?.service_name || 'No waiting queue';

      const hMap = servingMap(data.hospitalization?.serving_list || []);
      [8, 9].forEach(cid => {
        const el = document.getElementById(`h-counter-${cid}`);
        if (el) {
          el.textContent = hMap[String(cid)]?.queue_code || '—';
        }
      });

      const hNextCode = document.getElementById('h-next-code');
      const hNextService = document.getElementById('h-next-service');
      if (hNextCode) hNextCode.textContent = data.hospitalization?.next?.queue_code || '—';
      if (hNextService) hNextService.textContent = data.hospitalization?.next?.service_name || 'No waiting queue';

      const pServingCode = document.getElementById('p-serving-code');
      const pServingCounter = document.getElementById('p-serving-counter');
      const pServingService = document.getElementById('p-serving-service');
      const pNextCode = document.getElementById('p-next-code');
      const pNextCounter = document.getElementById('p-next-counter');
      const pNextService = document.getElementById('p-next-service');

      if (pServingCode) pServingCode.textContent = data.priority?.serving_main?.queue_code || '—';
      if (pServingCounter) pServingCounter.textContent = data.priority?.serving_main ? 'Counter 2' : '—';
      if (pServingService) pServingService.textContent = data.priority?.serving_main?.service_name || 'No serving queue';

      if (pNextCode) pNextCode.textContent = data.priority?.next?.queue_code || '—';
      if (pNextCounter) pNextCounter.textContent = data.priority?.next ? 'Counter 2' : '—';
      if (pNextService) pNextService.textContent = data.priority?.next?.service_name || 'No waiting queue';
    }

    async function refresh() {
      try {
        const res = await fetch(`board.php?ajax=1`, {
          cache: 'no-store'
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const data = await res.json();

        updateBoard(data);

        const mServing = data.membership?.serving_main?.queue_code || '—';
        const mServingCounter = data.membership?.serving_main?.counter_id ?
          `Counter ${data.membership.serving_main.counter_id}` :
          '—';

        const hServing = data.hospitalization?.serving_main?.queue_code || '—';
        const hServingCounter = data.hospitalization?.serving_main?.counter_id ?
          `Counter ${data.hospitalization.serving_main.counter_id}` :
          '—';

        const pServing = data.priority?.serving_main?.queue_code || '—';

        if (mServing !== last.M && mServing !== '—') {
          last.M = mServing;
          flashSection('membershipSection');
          speak(`Now serving ${mServing}. Please proceed to ${mServingCounter}.`);
        } else if (mServing === '—') {
          last.M = null;
        }

        if (hServing !== last.H && hServing !== '—') {
          last.H = hServing;
          flashSection('hospitalSection');
          speak(`Now serving ${hServing}. Please proceed to ${hServingCounter}.`);
        } else if (hServing === '—') {
          last.H = null;
        }

        if (pServing !== last.P && pServing !== '—') {
          last.P = pServing;
          flashSection('prioritySection');
          speak(`Priority number ${pServing}. Proceed to ground floor. Counter 2.`);
        } else if (pServing === '—') {
          last.P = null;
        }

      } catch (e) {}
    }

    refresh();
    setInterval(refresh, 2500);
  </script>
</body>

</html>
