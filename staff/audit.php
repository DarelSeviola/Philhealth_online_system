<?php
/* =========================================================
   START STAFF SESSION
   Only staff or admin can access this page
========================================================= */
session_name('staff_session');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
  header("Location: login.php");
  exit();
}

/* =========================================================
   LOAD REQUIRED FILES
========================================================= */
define('BASE_PATH', __DIR__ . '/../');
require_once BASE_PATH . 'config/db.php';
require_once BASE_PATH . 'config/helpers.php';

/* =========================================================
   SET LOCAL TIMEZONE
========================================================= */
date_default_timezone_set('Asia/Manila');

/* =========================================================
   CHECK DATABASE CONNECTION
========================================================= */
if (!$conn) {
  die("Database connection failed: " . mysqli_connect_error());
}

/* =========================================================
   GET FILTER VALUES
========================================================= */
$from  = $_GET['from'] ?? date('Y-m-d');
$to    = $_GET['to'] ?? date('Y-m-d');
$cat   = trim((string)($_GET['category'] ?? ''));
$q     = trim((string)($_GET['q'] ?? ''));
$actor = trim((string)($_GET['actor'] ?? ''));

/* =========================================================
   LOAD CATEGORIES FOR DROPDOWN
========================================================= */
$categories = [];

$resCats = $conn->query("
  SELECT category_id, category_name
  FROM queue_categories
  WHERE is_active = 1
  ORDER BY category_name ASC
");

if ($resCats) {
  while ($row = $resCats->fetch_assoc()) {
    $categories[] = $row;
  }
}

/* =========================================================
   BADGE HELPER FOR STATUS
========================================================= */
function status_badge(?string $s): array
{
  $s = strtolower(trim((string)$s));

  if ($s === '') return ['—', 'bg-white/70 text-slate-600 border-white/60'];

  if ($s === 'waiting')   return ['WAITING', 'bg-amber-50 text-amber-700 border-amber-100'];
  if ($s === 'serving')   return ['SERVING', 'bg-blue-50 text-blue-700 border-blue-100'];
  if ($s === 'done')      return ['DONE', 'bg-emerald-50 text-emerald-700 border-emerald-100'];
  if ($s === 'cancelled') return ['CANCELLED', 'bg-red-50 text-red-700 border-red-100'];

  if ($s === 'booked')     return ['BOOKED', 'bg-blue-50 text-blue-700 border-blue-100'];
  if ($s === 'checked_in') return ['CHECKED IN', 'bg-amber-50 text-amber-700 border-amber-100'];
  if ($s === 'completed')  return ['COMPLETED', 'bg-emerald-50 text-emerald-700 border-emerald-100'];

  return [strtoupper($s), 'bg-white/70 text-slate-700 border-white/60'];
}

/* =========================================================
   BADGE HELPER FOR CLIENT SOURCE
========================================================= */
function client_source_badge(?string $src): array
{
  $src = strtolower(trim((string)$src));

  if ($src === 'walkin') return ['WALK-IN', 'bg-slate-50 text-slate-700 border-slate-200'];
  if ($src === 'online') return ['ONLINE', 'bg-indigo-50 text-indigo-700 border-indigo-100'];

  return ['—', 'bg-white/70 text-slate-600 border-white/60'];
}

/* =========================================================
   CONVERT ACTION TO STATUS IF QUEUE STATUS IS EMPTY
========================================================= */
function action_to_status(?string $action): string
{
  $action = strtolower(trim((string)$action));

  return match ($action) {
    'book_appointment', 'reserve_appointment' => 'booked',
    'check_in', 'checked_in'                  => 'checked_in',
    'call_queue', 'enqueue', 'add_queue'      => 'waiting',
    'start_serving', 'serve', 'serving'       => 'serving',
    'mark_done', 'done'                       => 'done',
    'complete_appointment', 'completed'       => 'completed',
    'cancel_queue', 'cancelled', 'cancel'     => 'cancelled',
    default                                   => '',
  };
}

/* =========================================================
   BUILD SQL FILTERS
========================================================= */
$where  = [
  "al.created_at >= CONCAT(?, ' 00:00:00')",
  "al.created_at <= CONCAT(?, ' 23:59:59')"
];

$params = [$from, $to];
$types  = "ss";

/* Filter by category */
if ($cat !== '') {
  $where[] = "COALESCE(q.category_id, ap.category_id, al.category_id) = ?";
  $types  .= "i";
  $params[] = (int)$cat;
}

/* Filter by staff */
if ($actor !== '') {
  $where[] = "al.actor_name LIKE CONCAT('%', ?, '%')";
  $types  .= "s";
  $params[] = $actor;
}

/* Search filter */
if ($q !== '') {
  $where[] = "(
    COALESCE(q.queue_code, al.queue_code, '') LIKE CONCAT('%', ?, '%')
    OR al.details LIKE CONCAT('%', ?, '%')
    OR COALESCE(u.full_name, ap.walkin_name, '') LIKE CONCAT('%', ?, '%')
    OR s.service_name LIKE CONCAT('%', ?, '%')
    OR qc.category_name LIKE CONCAT('%', ?, '%')
  )";

  $types .= "sssss";
  $params[] = $q;
  $params[] = $q;
  $params[] = $q;
  $params[] = $q;
  $params[] = $q;
}

/* =========================================================
   MAIN AUDIT QUERY
   SHOW ONLY THE LATEST LOG PER TRANSACTION
========================================================= */
$sql = "
SELECT
  al.log_id,
  al.created_at,
  al.actor_name,
  al.actor_role,
  al.action,
  al.details,
  al.queue_code AS al_queue_code,

  COALESCE(q.queue_code, al.queue_code, '') AS queue_code,
  q.status AS queue_status,

  qc.category_name,
  s.service_name,

  COALESCE(u.full_name, ap.walkin_name, '—') AS client_name,
  ap.client_status AS type_of_client,
  ap.source AS client_source

FROM audit_logs al

INNER JOIN (
  SELECT
    COALESCE(queue_code, CONCAT('APPT-', appointment_id), CONCAT('LOG-', log_id)) AS grp_key,
    MAX(log_id) AS latest_log_id
  FROM audit_logs
  GROUP BY COALESCE(queue_code, CONCAT('APPT-', appointment_id), CONCAT('LOG-', log_id))
) latest
  ON latest.latest_log_id = al.log_id

LEFT JOIN appointments ap
  ON ap.appointment_id = al.appointment_id

LEFT JOIN users u
  ON u.user_id = ap.user_id

LEFT JOIN queue q
  ON q.appointment_id = ap.appointment_id

LEFT JOIN queue_categories qc
  ON qc.category_id = COALESCE(q.category_id, ap.category_id, al.category_id)

LEFT JOIN services s
  ON s.service_id = ap.service_id

WHERE " . implode(" AND ", $where) . "
ORDER BY al.log_id DESC
LIMIT 500
";

/* =========================================================
   RUN QUERY
========================================================= */
$stmt = $conn->prepare($sql);

if (!$stmt) {
  die("SQL prepare error: " . $conn->error);
}

$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
  die("Execute error: " . $stmt->error);
}

$result = $stmt->get_result();
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

/* =========================================================
   SIMPLE METRICS
========================================================= */
$total = count($rows);
$completedCount = 0;

foreach ($rows as $r) {
  $tmpStatus = trim((string)($r['queue_status'] ?? ''));
  if ($tmpStatus === '') {
    $tmpStatus = action_to_status($r['action'] ?? '');
  }

  if (in_array(strtolower($tmpStatus), ['done', 'completed'], true)) {
    $completedCount++;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Audit Logs</title>
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

<body class="min-h-screen text-slate-800">
  <div class="fixed inset-0 -z-10 lime-bg"></div>
  <div class="fixed inset-0 -z-10 grid-overlay pointer-events-none"></div>

  <div class="pointer-events-none fixed inset-0 overflow-hidden -z-10">
    <div class="absolute -top-52 -right-60 h-[34rem] w-[34rem] rounded-full bg-white blur-3xl opacity-20"></div>
    <div class="absolute -bottom-52 -left-60 h-[34rem] w-[34rem] rounded-full bg-brand-50 blur-3xl opacity-20"></div>
  </div>

  <div class="mx-auto max-w-6xl p-4 md:p-6 space-y-5">

    <header class="rounded-3xl border border-white/40 bg-white/75 backdrop-blur shadow-sm">
      <div class="p-4 md:p-5 flex flex-col lg:flex-row lg:items-center gap-4 justify-between">
        <div class="flex items-start gap-3">
          <div class="h-10 w-10 rounded-2xl bg-brand-50 border border-white/50 flex items-center justify-center overflow-hidden">
            <img src="../logo/philhealth_Logo.png" alt="PhilHealth Logo" class="h-10 w-10 object-contain">
          </div>

          <div>
            <h1 class="text-xl md:text-2xl font-extrabold tracking-tight">System Audit Trail</h1>

            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-600">
              <span class="font-semibold">Records:</span>

              <span class="px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 font-extrabold text-xs border border-brand-100">
                <?= (int)$total ?>
              </span>

              <?php if ($completedCount > 0): ?>
                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-extrabold text-xs border border-emerald-200 flex items-center gap-1">
                  <?= (int)$completedCount ?> completed
                </span>
              <?php endif; ?>

              <span class="hidden sm:inline">•</span>
              <span><?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <a href="serve.php" class="px-3 py-2 rounded-xl text-sm font-extrabold bg-white/70 border border-white/60 hover:bg-white transition">
            Back to Control
          </a>
        </div>
      </div>

      <div class="px-4 md:px-5 pb-5">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3">

          <div class="md:col-span-3">
            <label class="block text-xs font-bold text-slate-500 mb-1">From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
              class="w-full rounded-xl border border-white/60 bg-white/85 px-3 py-2 text-sm font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-200">
          </div>

          <div class="md:col-span-3">
            <label class="block text-xs font-bold text-slate-500 mb-1">To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"
              class="w-full rounded-xl border border-white/60 bg-white/85 px-3 py-2 text-sm font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-200">
          </div>

          <div class="md:col-span-3">
            <label class="block text-xs font-bold text-slate-500 mb-1">Category</label>
            <select name="category"
              class="w-full rounded-xl border border-white/60 bg-white/85 px-3 py-2 text-sm font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-200">
              <option value="">All Categories</option>
              <?php foreach ($categories as $c): ?>
                <?php $cid = (string)$c['category_id']; ?>
                <option value="<?= htmlspecialchars($cid) ?>" <?= ($cat === $cid) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['category_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="md:col-span-3">
            <label class="block text-xs font-bold text-slate-500 mb-1">Staff</label>
            <input type="text" name="actor" value="<?= htmlspecialchars($actor) ?>" placeholder="e.g., Juan Dela Cruz"
              class="w-full rounded-xl border border-white/60 bg-white/85 px-3 py-2 text-sm font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-200">
          </div>

          <div class="md:col-span-9">
            <label class="block text-xs font-bold text-slate-500 mb-1">Search (Queue code, details, client, service)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Queue code, details, client, service"
              class="w-full rounded-xl border border-white/60 bg-white/85 px-3 py-2 text-sm font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-200">
          </div>

          <div class="md:col-span-3 flex items-end gap-2">
            <button class="w-full rounded-xl bg-brand-700 hover:opacity-95 text-white py-2.5 text-sm font-extrabold shadow-sm transition active:scale-[.99]">
              Apply Filters
            </button>
            <a href="audit.php" class="w-full text-center rounded-xl border border-white/60 bg-white/80 hover:bg-white py-2.5 text-sm font-extrabold text-slate-700 transition">
              Reset
            </a>
          </div>
        </form>

        <p class="mt-3 text-xs text-slate-500">
          Showing up to <span class="font-bold">500</span> newest logs based on your filters.
        </p>
      </div>
    </header>

    <section class="rounded-3xl border border-white/40 bg-white/75 backdrop-blur shadow-sm overflow-hidden">
      <div class="overflow-x-hidden">
        <table class="w-full table-fixed text-left">
          <thead class="border-b border-white/50 bg-white/50">
            <tr class="text-xs font-extrabold text-slate-500 uppercase tracking-wide">
              <th class="px-6 py-3 w-28">Date/Time</th>
              <th class="px-6 py-3 w-28">Staff</th>
              <th class="px-5 py-3 w-32">Client Name</th>
              <th class="px-6 py-3 w-28">Type</th>
              <th class="px-6 py-3 w-28">Queue</th>
              <th class="px-6 py-3 w-44">Category / Service</th>
              <th class="px-9 py-3 w-26">Status</th>
              <th class="px-6 py-3">Details</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-white/40 text-sm">
            <?php if (!$rows): ?>
              <tr>
                <td colspan="8" class="px-4 py-10">
                  <div class="rounded-2xl border border-dashed border-white/60 bg-white/50 p-6 text-slate-600">
                    <div class="font-extrabold">No logs found</div>
                    <div class="mt-1 text-sm text-slate-500">Try adjusting the date range or filters.</div>
                  </div>
                </td>
              </tr>
            <?php endif; ?>

            <?php foreach ($rows as $r): ?>
              <?php
              $dt = strtotime($r['created_at'] ?? '');
              $timeTop = $dt ? date('h:i A', $dt) : '—';
              $dateBtm = $dt ? date('M d, Y', $dt) : '';

              $actorName = (string)($r['actor_name'] ?? '—');

              $clientName = trim((string)($r['client_name'] ?? '—'));
              $clientSource = (string)($r['client_source'] ?? '');
              [$srcLabel, $srcCls] = client_source_badge($clientSource);

              $typeOfClient = trim((string)($r['type_of_client'] ?? ''));
              if ($typeOfClient === '') $typeOfClient = '—';

              $queueCode = trim((string)($r['queue_code'] ?? ''));

              $categoryName = trim((string)($r['category_name'] ?? ''));
              $serviceName  = trim((string)($r['service_name'] ?? ''));

              $queueStatus = trim((string)($r['queue_status'] ?? ''));
              if ($queueStatus === '') {
                $queueStatus = action_to_status($r['action'] ?? '');
              }
              [$stLabel, $stCls] = status_badge($queueStatus);

              $details = (string)($r['details'] ?? '');
              ?>

              <tr class="odd:bg-white/15 hover:bg-white/35 transition align-top">
                <td class="px-4 py-3">
                  <div class="font-extrabold text-slate-800 leading-tight"><?= htmlspecialchars($timeTop) ?></div>
                  <div class="text-xs font-semibold text-slate-500 leading-tight mt-0.5"><?= htmlspecialchars($dateBtm) ?></div>
                </td>

                <td class="px-4 py-3">
                  <div class="font-extrabold text-slate-800"><?= htmlspecialchars(strtoupper($actorName)) ?></div>
                </td>

                <td class="px-4 py-3">
                  <?php if ($clientName !== '—'): ?>
                    <div class="font-semibold text-slate-700"><?= htmlspecialchars(strtoupper($clientName)) ?></div>
                    <div class="mt-1">
                      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold border <?= htmlspecialchars($srcCls) ?>">
                        <?= htmlspecialchars($srcLabel) ?>
                      </span>
                    </div>
                  <?php else: ?>
                    <span class="text-slate-400">—</span>
                  <?php endif; ?>
                </td>

                <td class="px-4 py-3">
                  <?php if ($typeOfClient !== '—'): ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold border bg-white/70 text-slate-700 border-white/60">
                      <?= htmlspecialchars($typeOfClient) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-slate-400">—</span>
                  <?php endif; ?>
                </td>

                <td class="px-4 py-3">
                  <?php if ($queueCode !== ''): ?>
                    <span class="font-extrabold text-brand-700"><?= htmlspecialchars($queueCode) ?></span>
                  <?php else: ?>
                    <span class="text-slate-400">—</span>
                  <?php endif; ?>
                </td>

                <td class="px-4 py-3">
                  <?php if ($categoryName !== ''): ?>
                    <div class="text-[11px] font-extrabold text-slate-700 uppercase tracking-wide">
                      <?= htmlspecialchars($categoryName) ?>
                    </div>
                    <div class="mt-1 text-sm font-semibold text-slate-800">
                      <?= htmlspecialchars($serviceName !== '' ? $serviceName : '—') ?>
                    </div>
                  <?php else: ?>
                    <span class="text-slate-400">—</span>
                  <?php endif; ?>
                </td>

                <td class="px-4 py-3">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold border <?= htmlspecialchars($stCls) ?>">
                    <?= htmlspecialchars($stLabel) ?>
                  </span>
                </td>

                <td class="px-4 py-3">
                  <?php if (trim($details) !== ''): ?>
                    <div class="text-slate-600 whitespace-normal break-words leading-relaxed">
                      <?= htmlspecialchars($details) ?>
                    </div>
                  <?php else: ?>
                    <span class="text-slate-400">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-4 py-3 border-t border-white/40 bg-white/40">
        <div class="text-xs text-slate-500">Tip: Use Staff + Search together to quickly locate a transaction.</div>
        <div class="text-xs font-bold text-slate-500">Latest first • Max 500 rows</div>
      </div>
    </section>

  </div>
</body>

</html>