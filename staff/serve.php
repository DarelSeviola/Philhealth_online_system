<?php
// Start staff session
session_name('staff_session');
session_start();

// Allow only staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
  header("Location: login.php");
  exit();
}

// Load needed files
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/csrf.php";
require_once __DIR__ . "/../config/helpers.php";

// Set local time
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
$currentCounterId = isset($_SESSION['counter_id']) ? (int)$_SESSION['counter_id'] : 0;

// Queue group counters
const PRIORITY_COUNTER = 2;
const MEMBERSHIP_COUNTERS = [4, 6, 7];
const BENEFIT_COUNTERS = [8, 9];

/* =========================================================
   Queue group helper functions
========================================================= */

// Return counters for each group
function get_group_counters(string $group): array
{
  if ($group === 'priority') {
    return [2];
  }

  if ($group === 'membership') {
    return [4, 6, 7];
  }

  if ($group === 'hospitalization') {
    return [8, 9];
  }

  return [];
}

// Initialize active counters for a group if missing
function ensure_group_active_counters_initialized(string $group, int $currentCounterId = 0): void
{
  if (!isset($_SESSION['active_counters']) || !is_array($_SESSION['active_counters'])) {
    $_SESSION['active_counters'] = [];
  }

  if (isset($_SESSION['active_counters'][$group]) && is_array($_SESSION['active_counters'][$group])) {
    return;
  }

  $groupCounters = get_group_counters($group);

  // Default: if current counter belongs to this group, make it active initially.
  // Otherwise, start with no active counters until staff checks them manually.
  if ($currentCounterId > 0 && in_array($currentCounterId, $groupCounters, true)) {
    $_SESSION['active_counters'][$group] = [$currentCounterId];
  } else {
    $_SESSION['active_counters'][$group] = [];
  }
}

// Return manually selected active counters for the selected group
function get_active_group_counters(string $group): array
{
  $groupCounters = get_group_counters($group);
  $saved = $_SESSION['active_counters'][$group] ?? [];

  if (!is_array($saved)) {
    return [];
  }

  $saved = array_map('intval', $saved);
  $saved = array_values(array_intersect($groupCounters, $saved));
  sort($saved);

  return $saved;
}

// Build SQL rules for each group
function build_group_guard(string $group): array
{
  $join = "
    LEFT JOIN appointments a ON a.appointment_id = q.appointment_id
    LEFT JOIN services s ON s.service_id = a.service_id
  ";

  $where = "";
  $types = "";
  $params = [];

  if ($group === 'priority') {
    $where = "AND a.client_status IN ('Senior Citizen','PWD','Pregnant')";
    return [$join, $where, $types, $params];
  }

  if ($group === 'membership') {
    $where = "AND q.category_id = 1 AND (a.client_status IS NULL OR a.client_status = 'Regular')";
    return [$join, $where, $types, $params];
  }

  if ($group === 'hospitalization') {
    $where = "AND q.category_id = 2 AND (a.client_status IS NULL OR a.client_status = 'Regular')";
    return [$join, $where, $types, $params];
  }

  $where = "AND 1=0";
  return [$join, $where, $types, $params];
}

// Count waiting clients in one queue group
function get_group_waiting_count(mysqli $conn, string $today, string $group): int
{
  [$guardJoin, $guardWhere, $guardTypes, $guardParams] = build_group_guard($group);

  $sql = "
    SELECT COUNT(*) AS c
    FROM queue q
    $guardJoin
    WHERE q.queue_date = ?
      AND q.status = 'waiting'
      $guardWhere
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return 0;
  }

  $types = "s" . $guardTypes;
  $params = array_merge([$today], $guardParams);

  $stmt->bind_param($types, ...$params);
  $stmt->execute();

  $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();

  return $count;
}

// Find first free active counter in selected group
function get_first_free_counter(mysqli $conn, string $today, string $group): ?int
{
  $counters = get_active_group_counters($group);
  if (!$counters) {
    return null;
  }

  foreach ($counters as $cid) {
    $stmt = $conn->prepare("
      SELECT queue_id
      FROM queue
      WHERE queue_date = ?
        AND counter_id = ?
        AND status = 'serving'
      LIMIT 1
    ");
    if (!$stmt) {
      continue;
    }

    $stmt->bind_param("si", $today, $cid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
      return $cid;
    }
  }

  return null;
}

// Count how many active counters are currently serving in this group
function get_group_serving_count(mysqli $conn, string $today, string $group): int
{
  [$guardJoin, $guardWhere, $guardTypes, $guardParams] = build_group_guard($group);
  $counters = get_active_group_counters($group);

  if (!$counters) {
    return 0;
  }

  $placeholders = implode(',', array_fill(0, count($counters), '?'));
  $types = "s" . str_repeat("i", count($counters)) . $guardTypes;
  $params = array_merge([$today], array_map('intval', $counters), $guardParams);

  $sql = "
    SELECT COUNT(DISTINCT q.counter_id) AS c
    FROM queue q
    $guardJoin
    WHERE q.queue_date = ?
      AND q.counter_id IN ($placeholders)
      AND q.status = 'serving'
      $guardWhere
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return 0;
  }

  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return (int)($row['c'] ?? 0);
}

// Get one current serving queue in selected group (oldest among active counters)
function get_group_serving(mysqli $conn, string $today, string $group): ?array
{
  [$guardJoin, $guardWhere, $guardTypes, $guardParams] = build_group_guard($group);
  $counters = get_active_group_counters($group);

  if (!$counters) {
    return null;
  }

  $placeholders = implode(',', array_fill(0, count($counters), '?'));
  $types = "s" . str_repeat("i", count($counters)) . $guardTypes;
  $params = array_merge([$today], array_map('intval', $counters), $guardParams);

  $sql = "
    SELECT
      q.queue_id,
      q.appointment_id,
      q.queue_code,
      q.category_id,
      q.counter_id,
      qc.category_name,
      COALESCE(s.service_name, '') AS service_name
    FROM queue q
    JOIN queue_categories qc ON qc.category_id = q.category_id
    $guardJoin
    WHERE q.queue_date = ?
      AND q.counter_id IN ($placeholders)
      AND q.status = 'serving'
      $guardWhere
    ORDER BY q.created_at ASC
    LIMIT 1
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $row ?: null;
}

// Get waiting list in selected group
function get_group_waiting_list(mysqli $conn, string $today, string $group): array
{
  [$guardJoin, $guardWhere, $guardTypes, $guardParams] = build_group_guard($group);

  $sql = "
    SELECT
      q.queue_id,
      q.queue_code,
      q.created_at,
      qc.category_name,
      COALESCE(s.service_name, '—') AS service_name
    FROM queue q
    JOIN queue_categories qc ON qc.category_id = q.category_id
    $guardJoin
    WHERE q.queue_date = ?
      AND q.status = 'waiting'
      $guardWhere
    ORDER BY q.created_at ASC
    LIMIT 30
  ";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return [];
  }

  $types = "s" . $guardTypes;
  $params = array_merge([$today], $guardParams);

  $stmt->bind_param($types, ...$params);
  $stmt->execute();

  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  return $rows ?: [];
}

// Return live summary for the selected group
function get_group_live_summary(mysqli $conn, string $today, string $group): array
{
  $allCounters = get_group_counters($group);
  $activeCounters = get_active_group_counters($group);
  $totalCounters = count($activeCounters);
  $waitingCount = get_group_waiting_count($conn, $today, $group);
  $servingCount = get_group_serving_count($conn, $today, $group);
  $freeCounter = get_first_free_counter($conn, $today, $group);
  $serving = get_group_serving($conn, $today, $group);

  return [
    'group' => $group,
    'group_counters' => $allCounters,
    'active_counters' => $activeCounters,
    'total_counters' => $totalCounters,
    'serving_count' => $servingCount,
    'waiting_count' => $waitingCount,
    'free_counter' => $freeCounter,
    'free_count' => max(0, $totalCounters - $servingCount),
    'can_call_next' => ($freeCounter !== null && $waitingCount > 0),
    'serving' => $serving
  ];
}

/* =========================================================
   Selected queue group setup
========================================================= */

$selectedQueueGroup = $_SESSION['selected_queue_group'] ?? 'membership';
if (!in_array($selectedQueueGroup, ['priority', 'membership', 'hospitalization'], true)) {
  $selectedQueueGroup = 'membership';
  $_SESSION['selected_queue_group'] = $selectedQueueGroup;
}

// Ensure active counters exist for all groups
ensure_group_active_counters_initialized('priority', $currentCounterId);
ensure_group_active_counters_initialized('membership', $currentCounterId);
ensure_group_active_counters_initialized('hospitalization', $currentCounterId);

/* =========================================================
   AJAX requests
========================================================= */

// Return waiting list
if (isset($_GET['ajax']) && $_GET['ajax'] === 'queue') {
  header('Content-Type: application/json; charset=utf-8');

  $group = trim((string)($_GET['group'] ?? ''));
  if ($group === '') {
    echo json_encode([]);
    exit();
  }

  echo json_encode(get_group_waiting_list($conn, $today, $group), JSON_UNESCAPED_UNICODE);
  exit();
}

// Return one current serving queue
if (isset($_GET['ajax']) && $_GET['ajax'] === 'serving') {
  header('Content-Type: application/json; charset=utf-8');

  $group = trim((string)($_GET['group'] ?? ''));
  if ($group === '') {
    echo json_encode(null);
    exit();
  }

  echo json_encode(get_group_serving($conn, $today, $group), JSON_UNESCAPED_UNICODE);
  exit();
}

// Return live summary for button logic and counters
if (isset($_GET['ajax']) && $_GET['ajax'] === 'summary') {
  header('Content-Type: application/json; charset=utf-8');

  $group = trim((string)($_GET['group'] ?? ''));
  if ($group === '') {
    echo json_encode([
      'group' => '',
      'group_counters' => [],
      'active_counters' => [],
      'total_counters' => 0,
      'serving_count' => 0,
      'waiting_count' => 0,
      'free_counter' => null,
      'free_count' => 0,
      'can_call_next' => false,
      'serving' => null
    ], JSON_UNESCAPED_UNICODE);
    exit();
  }

  echo json_encode(get_group_live_summary($conn, $today, $group), JSON_UNESCAPED_UNICODE);
  exit();
}

/* =========================================================
   Flash messages and selected queue group
========================================================= */

// Flash messages
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Waiting counts per group
$priorityWaiting   = get_group_waiting_count($conn, $today, 'priority');
$membershipWaiting = get_group_waiting_count($conn, $today, 'membership');
$hospitalWaiting   = get_group_waiting_count($conn, $today, 'hospitalization');

// Current selected queue group
$selectedQueueGroup = $_SESSION['selected_queue_group'] ?? 'membership';
if (!in_array($selectedQueueGroup, ['priority', 'membership', 'hospitalization'], true)) {
  $selectedQueueGroup = 'membership';
  $_SESSION['selected_queue_group'] = $selectedQueueGroup;
}

// Name for selected queue group
$selectedGroupName = 'Membership';
if ($selectedQueueGroup === 'priority') {
  $selectedGroupName = 'Priority';
} elseif ($selectedQueueGroup === 'hospitalization') {
  $selectedGroupName = 'Benefit Availment';
}

$initialSummary = get_group_live_summary($conn, $today, $selectedQueueGroup);
$selectedGroupCounters = get_group_counters($selectedQueueGroup);
$selectedActiveCounters = get_active_group_counters($selectedQueueGroup);

/* =========================================================
   Form actions
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();

  $action = $_POST['action'] ?? '';

  // Change queue group
  if ($action === 'set_group') {
    $newGroup = trim((string)($_POST['queue_group'] ?? ''));

    if (!in_array($newGroup, ['priority', 'membership', 'hospitalization'], true)) {
      $_SESSION['error'] = "Invalid queue group selected.";
    } else {
      $_SESSION['selected_queue_group'] = $newGroup;
      ensure_group_active_counters_initialized($newGroup, $currentCounterId);
      $_SESSION['success'] = "Queue group set successfully.";
    }

    header("Location: serve.php");
    exit();
  }

  // Save manual active counters
  if ($action === 'set_active_counters') {
    $group = $_SESSION['selected_queue_group'] ?? 'membership';
    $allowed = get_group_counters($group);
    $posted = $_POST['active_counters'] ?? [];
    if (!is_array($posted)) {
      $posted = [];
    }

    $newActive = [];
    foreach ($posted as $cid) {
      $cid = (int)$cid;
      if (in_array($cid, $allowed, true)) {
        $newActive[] = $cid;
      }
    }

    $newActive = array_values(array_unique($newActive));
    sort($newActive);

    $_SESSION['active_counters'][$group] = $newActive;
    $_SESSION['success'] = "Active counters updated.";

    header("Location: serve.php");
    exit();
  }

  $group = $_SESSION['selected_queue_group'] ?? 'membership';

  if (!in_array($action, ['call_next', 'mark_done'], true)) {
    $_SESSION['error'] = "Invalid action.";
    header("Location: serve.php");
    exit();
  }

  $activeCounters = get_active_group_counters($group);
  if (!$activeCounters) {
    $_SESSION['error'] = "Please select at least one active counter for this queue group.";
    header("Location: serve.php");
    exit();
  }

  $conn->begin_transaction();

  try {
    // Call next client
    if ($action === 'call_next') {
      [$guardJoin, $guardWhere, $guardTypes, $guardParams] = build_group_guard($group);

      $sql = "
        SELECT
          q.queue_id,
          q.appointment_id,
          q.queue_code,
          q.category_id
        FROM queue q
        $guardJoin
        WHERE q.queue_date = ?
          AND q.status = 'waiting'
          $guardWhere
        ORDER BY q.created_at ASC
        LIMIT 1
        FOR UPDATE
      ";

      $stmt = $conn->prepare($sql);
      if (!$stmt) {
        throw new Exception("Failed to prepare next queue query.");
      }

      $types2 = "s" . $guardTypes;
      $params2 = array_merge([$today], $guardParams);

      $stmt->bind_param($types2, ...$params2);
      $stmt->execute();
      $next = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$next) {
        throw new Exception("No waiting clients in this queue group.");
      }

      $freeCounter = get_first_free_counter($conn, $today, $group);

      if ($freeCounter === null) {
        throw new Exception("No free active counter available in this group.");
      }

      $qid    = (int)$next['queue_id'];
      $cat_id = (int)$next['category_id'];
      $aid    = !empty($next['appointment_id']) ? (int)$next['appointment_id'] : null;

      $stmt = $conn->prepare("
        UPDATE queue
        SET status = 'serving',
            counter_id = ?
        WHERE queue_id = ?
          AND status = 'waiting'
      ");
      if (!$stmt) {
        throw new Exception("Failed to update queue status.");
      }

      $stmt->bind_param("ii", $freeCounter, $qid);
      $stmt->execute();

      if ($stmt->affected_rows !== 1) {
        $stmt->close();
        throw new Exception("Queue was already processed by another staff.");
      }

      $stmt->close();

      if ($aid !== null) {
        $stmt = $conn->prepare("
          UPDATE appointments
          SET counter_id = ?
          WHERE appointment_id = ?
        ");
        if ($stmt) {
          $stmt->bind_param("ii", $freeCounter, $aid);
          $stmt->execute();
          $stmt->close();
        }
      }

      log_audit(
        $conn,
        'call_next',
        "Called to group {$group} / Counter #{$freeCounter}",
        $qid,
        $next['queue_code'],
        $cat_id,
        $freeCounter,
        $aid
      );

      $_SESSION['success'] = "Now serving: " . $next['queue_code'] . " at Counter " . $freeCounter;
    }

    // Mark one current serving as done among active counters
    if ($action === 'mark_done') {
      $serving = get_group_serving($conn, $today, $group);

      if (!$serving) {
        throw new Exception("No active serving queue in this group.");
      }

      $qid        = (int)$serving['queue_id'];
      $cat_id     = (int)$serving['category_id'];
      $counter_id = (int)$serving['counter_id'];
      $aid        = !empty($serving['appointment_id']) ? (int)$serving['appointment_id'] : null;

      $stmt = $conn->prepare("
        UPDATE queue
        SET status = 'done'
        WHERE queue_id = ?
          AND counter_id = ?
          AND status = 'serving'
      ");
      if (!$stmt) {
        throw new Exception("Failed to update serving queue.");
      }

      $stmt->bind_param("ii", $qid, $counter_id);
      $stmt->execute();

      if ($stmt->affected_rows !== 1) {
        $stmt->close();
        throw new Exception("Serving queue could not be completed.");
      }

      $stmt->close();

      if ($aid !== null) {
        $stmt = $conn->prepare("
          UPDATE appointments
          SET status = 'completed'
          WHERE appointment_id = ?
        ");
        if ($stmt) {
          $stmt->bind_param("i", $aid);
          $stmt->execute();
          $stmt->close();
        }
      }

      log_audit(
        $conn,
        'mark_done',
        "Service completed",
        $qid,
        $serving['queue_code'],
        $cat_id,
        $counter_id,
        $aid
      );

      $_SESSION['success'] = "Completed: " . $serving['queue_code'];
    }

    $conn->commit();
  } catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
  }

  header("Location: serve.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff Queue Control</title>

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
      opacity: 0.28;
    }
  </style>
</head>

<body class="min-h-screen text-slate-900">
  <div class="fixed inset-0 -z-10 lime-bg"></div>
  <div class="fixed inset-0 -z-10 grid-overlay pointer-events-none"></div>

  <div class="pointer-events-none fixed inset-0 overflow-hidden -z-10">
    <div class="absolute -top-52 -right-60 h-[34rem] w-[34rem] rounded-full bg-white blur-3xl opacity-20"></div>
    <div class="absolute -bottom-52 -left-60 h-[34rem] w-[34rem] rounded-full bg-brand-50 blur-3xl opacity-20"></div>
  </div>

  <div class="mx-auto max-w-6xl p-4 md:p-6 space-y-5">

    <header class="rounded-3xl border border-white/40 bg-white/75 backdrop-blur shadow-sm overflow-hidden">
      <div class="p-4 md:p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-start gap-3">
          <div class="h-10 w-10 rounded-2xl bg-brand-50 flex items-center justify-center overflow-hidden border border-white/50">
            <img src="../logo/philhealth_Logo.png" alt="PhilHealth Logo" class="h-10 w-10 object-contain">
          </div>

          <div>
            <h1 class="text-xl md:text-2xl font-extrabold tracking-tight">Staff Queue Control</h1>
            <div class="mt-1 flex flex-wrap items-center gap-1 text-sm text-slate-600">
              <span class="inline-block h-2 w-2 rounded-full bg-brand-700"></span>
              <span><?php echo e(date('M d, Y')); ?></span>
              <span class="hidden sm:inline">•</span>
              <span class="hidden sm:inline">Auto refresh: 5s</span>
            </div>
          </div>
        </div>

        <div class="flex flex-col lg:flex-row lg:items-center gap-3 lg:gap-4">
          <form method="POST" action="serve.php" class="flex items-center gap-2 flex-1 min-w-0">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="set_group" />

            <select
              name="queue_group"
              class="w-full md:w-[520px] rounded-xl border border-white/60 bg-white/90 px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-200"
              onchange="this.form.submit()">
              <option value="priority" <?php echo $selectedQueueGroup === 'priority' ? 'selected' : ''; ?>>
                Special Lane (Counter 2) — <?php echo (int)$priorityWaiting; ?> waiting
              </option>
              <option value="membership" <?php echo $selectedQueueGroup === 'membership' ? 'selected' : ''; ?>>
                Membership (Counters 4, 6, 7) — <?php echo (int)$membershipWaiting; ?> waiting
              </option>
              <option value="hospitalization" <?php echo $selectedQueueGroup === 'hospitalization' ? 'selected' : ''; ?>>
                Benefit Availment (Counters 8, 9) — <?php echo (int)$hospitalWaiting; ?> waiting
              </option>
            </select>
          </form>

          <div class="flex items-center gap-2 whitespace-nowrap shrink-0">
            <a href="board.php" class="px-4 py-2 rounded-xl text-sm font-bold bg-white/70 border border-white/60 hover:bg-white transition">Board</a>
            <a href="../kiosk/index.php" class="px-4 py-2 rounded-xl text-sm font-bold bg-white/70 border border-white/60 hover:bg-white transition">Kiosk</a>
            <a href="audit.php" class="px-4 py-2 rounded-xl text-sm font-bold bg-white/70 border border-white/60 hover:bg-white transition">Audit</a>
            <a href="logout.php" class="px-4 py-2 rounded-xl text-sm font-bold text-white bg-brand-700 hover:opacity-95 transition">Logout</a>
          </div>
        </div>
      </div>

      <?php if ($success): ?>
        <div class="px-4 md:px-5 pb-4">
          <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm font-semibold">
            <?php echo e($success); ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="px-4 md:px-5 pb-4">
          <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm font-semibold">
            <?php echo e($error); ?>
          </div>
        </div>
      <?php endif; ?>
    </header>

    <main class="grid grid-cols-1 lg:grid-cols-12 gap-5">

      <section class="lg:col-span-4 space-y-5">
        <div class="rounded-3xl border border-white/40 bg-white/75 backdrop-blur shadow-sm p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="text-xs font-bold text-slate-500 uppercase tracking-wide">Selected Queue Group</div>
              <div class="mt-1 text-lg font-extrabold text-brand-700">
                <?php echo e($selectedGroupName); ?>
              </div>
            </div>

            <div id="servingPill" class="hidden shrink-0 text-xs font-extrabold px-2.5 py-1 rounded-full bg-brand-50 text-brand-700 border border-brand-100">
              Serving
            </div>
          </div>

          <div class="mt-2 text-xs text-slate-500">
            <span id="counterSummary">
              <?php echo (int)$initialSummary['serving_count']; ?> serving • <?php echo (int)$initialSummary['free_count']; ?> free
            </span>
          </div>

          <div class="mt-4 rounded-2xl border border-white/60 bg-white/80 p-4">
            <div class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Counter Availability</div>

            <form method="POST" class="space-y-3">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="set_active_counters" />

              <?php foreach ($selectedGroupCounters as $cid): ?>
                <?php $checked = in_array($cid, $selectedActiveCounters, true); ?>
                <label class="flex items-center justify-between rounded-xl border border-white/60 bg-white/90 px-3 py-2">
                  <span class="text-sm font-semibold text-slate-800">
                    Counter <?php echo (int)$cid; ?>
                  </span>
                  <span class="flex items-center gap-2">
                    <span class="text-xs font-bold <?php echo $checked ? 'text-emerald-700' : 'text-slate-500'; ?>">
                      <?php echo $checked ? 'Active' : 'Inactive'; ?>
                    </span>
                    <input
                      type="checkbox"
                      name="active_counters[]"
                      value="<?php echo (int)$cid; ?>"
                      <?php echo $checked ? 'checked' : ''; ?>
                      class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-200">
                  </span>
                </label>
              <?php endforeach; ?>

              <button
                type="submit"
                class="w-full rounded-xl border border-white/60 bg-white/80 hover:bg-white text-slate-800 py-2.5 text-sm font-extrabold transition active:scale-[.99]">
                Save Counter Status
              </button>
            </form>
          </div>

          <div class="mt-4 rounded-2xl border border-white/60 bg-white/80 p-4">
            <div class="text-xs font-bold text-slate-500 uppercase tracking-wide">Now Serving</div>
            <div id="servingCode" class="mt-2 text-4xl font-extrabold tracking-tight text-slate-300">—</div>
            <div id="servingCat" class="mt-1 text-xs font-bold text-slate-500 uppercase"></div>
            <div id="servingSvc" class="mt-1 text-sm font-semibold text-slate-700"></div>

            <div class="mt-4 grid grid-cols-1 gap-2">
              <form method="POST">
                <?php echo csrf_field(); ?>
                <button
                  id="callNextBtn"
                  name="action"
                  value="call_next"
                  class="w-full rounded-xl bg-brand-700 hover:opacity-95 text-white py-3 text-sm font-extrabold shadow-sm disabled:opacity-40 disabled:cursor-not-allowed transition active:scale-[.99]"
                  <?php echo $initialSummary['can_call_next'] ? '' : 'disabled'; ?>>
                  Call Next
                </button>
              </form>

              <form method="POST" id="doneForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="mark_done" />

                <button
                  type="button"
                  id="doneBtn"
                  onclick="openDoneModalFromServing()"
                  class="w-full rounded-xl border border-white/60 bg-white/80 hover:bg-white text-slate-800 py-3 text-sm font-extrabold disabled:opacity-40 disabled:cursor-not-allowed transition active:scale-[.99]">
                  Mark Done
                </button>
              </form>
            </div>

            <p class="mt-3 text-xs text-slate-500">
              Call Next is disabled when no active free counter is available or waiting is 0.
            </p>
          </div>
        </div>

        <div class="rounded-3xl border border-white/40 bg-white/75 backdrop-blur shadow-sm p-5">
          <div class="text-sm font-extrabold">Quick Rules</div>
          <ul class="mt-2 text-sm text-slate-600 space-y-1 list-disc pl-5">
            <li>FCFS by queue group</li>
            <li>Staff manually sets which counters are active or inactive</li>
            <li>Only active counters receive the next queue</li>
            <li>Mark Done completes one active serving queue in the group</li>
          </ul>
        </div>
      </section>

      <section class="lg:col-span-8 rounded-3xl border border-white/40 bg-white/75 backdrop-blur shadow-sm p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h2 class="text-lg md:text-xl font-extrabold">Waiting List</h2>
            <p class="text-sm text-slate-500">Shared waiting line for this queue group (up to 30)</p>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-500 uppercase">Waiting</span>
            <span id="queueCounterBadge" class="text-xs font-extrabold px-2.5 py-1 rounded-full bg-brand-50 text-brand-700 border border-brand-100">
              <?php echo (int)$initialSummary['waiting_count']; ?>
            </span>
          </div>
        </div>

        <div class="mt-4">
          <div id="queueList" class="space-y-2">
            <div class="text-slate-400 text-sm italic">Loading...</div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <div id="confirmModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>

    <div class="relative min-h-full flex items-center justify-center p-4">
      <div class="w-full max-w-md rounded-3xl bg-white/90 border border-white/60 shadow-2xl p-6 backdrop-blur">
        <div class="flex items-start gap-3">
          <div class="h-10 w-10 rounded-2xl bg-emerald-50 flex items-center justify-center">
            <svg class="h-5 w-5 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 6L9 17l-5-5" />
            </svg>
          </div>

          <div class="flex-1">
            <div class="text-lg font-extrabold">Confirm Completion</div>
            <p class="mt-1 text-sm text-slate-600">
              Mark <span id="modalQueueCode" class="font-extrabold text-brand-700"></span> as done?
            </p>
          </div>
        </div>

        <div class="mt-5 flex gap-2">
          <button
            type="button"
            onclick="closeDoneModal()"
            class="flex-1 rounded-xl border border-white/60 bg-white/80 hover:bg-white py-3 text-sm font-extrabold text-slate-800 transition">
            Cancel
          </button>

          <button
            type="button"
            id="confirmDoneBtn"
            class="flex-1 rounded-xl bg-emerald-600 hover:opacity-95 py-3 text-sm font-extrabold text-white transition flex items-center justify-center gap-2">
            <span id="btnText">Confirm</span>
            <span id="btnSpinner" class="hidden h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const selectedQueueGroup = <?php echo json_encode($selectedQueueGroup); ?>;

    const queueList = document.getElementById('queueList');
    const badge = document.getElementById('queueCounterBadge');
    const callBtn = document.getElementById('callNextBtn');
    const servingCodeEl = document.getElementById('servingCode');
    const servingCatEl = document.getElementById('servingCat');
    const servingSvcEl = document.getElementById('servingSvc');
    const doneBtn = document.getElementById('doneBtn');
    const servingPill = document.getElementById('servingPill');
    const counterSummaryEl = document.getElementById('counterSummary');

    let servingSnapshot = null;
    let groupSummary = <?php echo json_encode($initialSummary, JSON_UNESCAPED_UNICODE); ?>;

    function rowHtml(q, i) {
      const code = (q.queue_code ?? '').toString();
      const cat = (q.category_name ?? '').toString();
      const svc = (q.service_name ?? '').toString();

      return `
        <div class="flex items-center justify-between rounded-2xl border border-white/60 bg-white/80 p-4 hover:bg-white transition">
          <div class="min-w-0">
            <div class="text-lg font-extrabold tracking-tight text-slate-900">${code}</div>
            <div class="mt-0.5 text-xs font-bold text-slate-500 uppercase">${cat}</div>
            ${svc ? `<div class="mt-1 text-sm font-semibold text-slate-700">${svc}</div>` : ``}
          </div>
          <span class="shrink-0 text-xs font-extrabold px-2.5 py-1 rounded-full bg-brand-50 text-brand-700 border border-brand-100">
            #${i + 1}
          </span>
        </div>
      `;
    }

    async function loadWaiting() {
      try {
        const res = await fetch(`serve.php?ajax=queue&group=${encodeURIComponent(selectedQueueGroup)}`, {
          cache: "no-store"
        });
        if (!res.ok) throw new Error("HTTP " + res.status);

        const data = await res.json();
        badge.textContent = data.length;

        if (!data.length) {
          queueList.innerHTML = '<div class="rounded-2xl border border-dashed border-white/60 bg-white/50 p-6 text-sm text-slate-600">No clients waiting in this queue group.</div>';
        } else {
          queueList.innerHTML = data.map(rowHtml).join('');
        }
      } catch (e) {
        badge.textContent = 0;
        queueList.innerHTML = '<div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">Failed to load waiting list.</div>';
      }
    }

    async function loadSummary() {
      try {
        const res = await fetch(`serve.php?ajax=summary&group=${encodeURIComponent(selectedQueueGroup)}`, {
          cache: "no-store"
        });
        if (!res.ok) throw new Error("HTTP " + res.status);

        groupSummary = await res.json();
        servingSnapshot = groupSummary.serving ?? null;

        const servingCount = Number(groupSummary.serving_count || 0);
        const freeCount = Number(groupSummary.free_count || 0);
        const waitingCount = Number(groupSummary.waiting_count || 0);
        const freeCounter = groupSummary.free_counter;

        counterSummaryEl.textContent = `${servingCount} serving • ${freeCount} free`;

        if (!servingSnapshot) {
          servingCodeEl.textContent = '—';
          servingCodeEl.className = 'mt-2 text-4xl font-extrabold tracking-tight text-slate-300';
          servingCatEl.textContent = '';
          servingSvcEl.textContent = '';
          doneBtn.disabled = true;
          servingPill.classList.add('hidden');
        } else {
          servingCodeEl.textContent = servingSnapshot.queue_code ?? '—';
          servingCodeEl.className = 'mt-2 text-4xl font-extrabold tracking-tight text-brand-700';
          servingCatEl.textContent = servingSnapshot.category_name ?
            servingSnapshot.category_name.toUpperCase() + ' • COUNTER ' + servingSnapshot.counter_id :
            '';
          servingSvcEl.textContent = servingSnapshot.service_name ? servingSnapshot.service_name : '';
          doneBtn.disabled = false;
          servingPill.classList.remove('hidden');
        }

        callBtn.disabled = !(freeCounter !== null && waitingCount > 0);
      } catch (e) {
        servingSnapshot = null;
        doneBtn.disabled = true;
        callBtn.disabled = true;
        servingPill.classList.add('hidden');
        counterSummaryEl.textContent = '0 serving • 0 free';
      }
    }

    async function refreshAll() {
      await loadSummary();
      await loadWaiting();
    }

    setInterval(refreshAll, 5000);
    refreshAll();

    let activeFormId = null;

    function openDoneModalFromServing() {
      if (!servingSnapshot) return;
      openDoneModal(servingSnapshot.queue_code, 'doneForm');
    }

    function openDoneModal(queueCode, formId) {
      activeFormId = formId;
      document.getElementById('modalQueueCode').innerText = queueCode;
      document.getElementById('confirmModal').classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }

    function closeDoneModal() {
      document.getElementById('confirmModal').classList.add('hidden');
      document.body.style.overflow = '';
      activeFormId = null;

      const btn = document.getElementById('confirmDoneBtn');
      btn.disabled = false;
      document.getElementById('btnText').innerText = "Confirm";
      document.getElementById('btnSpinner').classList.add('hidden');
    }

    document.getElementById('confirmDoneBtn').addEventListener('click', function() {
      if (!activeFormId) return;

      this.disabled = true;
      document.getElementById('btnText').innerText = "Processing...";
      document.getElementById('btnSpinner').classList.remove('hidden');
      document.getElementById(activeFormId).submit();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeDoneModal();
    });
  </script>
</body>

</html>
