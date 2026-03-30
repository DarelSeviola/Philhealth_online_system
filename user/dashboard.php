<?php
// Start user session
session_name('user_session');

// Set session cookie settings
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',
  'secure' => false, // Change to true if using HTTPS
  'httponly' => true,
  'samesite' => 'Lax'
]);

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

// Show welcome modal only once per session
$showWelcomeModal = false;
if (empty($_SESSION['welcome_seen'])) {
  $_SESSION['welcome_seen'] = 1;
  $showWelcomeModal = true;
}

// Basic user info
$user_id = (int) $_SESSION['user_id'];
$today   = date('Y-m-d');

// Count upcoming appointments
$stmt = $conn->prepare("
  SELECT COUNT(*) AS cnt
  FROM appointments
  WHERE user_id = ?
    AND appointment_date >= ?
    AND status IN ('booked','checked_in')
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$upcoming_count = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

// Count checked-in appointments today
$stmt = $conn->prepare("
  SELECT COUNT(*) AS cnt
  FROM appointments a
  JOIN queue q ON q.appointment_id = a.appointment_id
  WHERE a.user_id = ?
    AND a.appointment_date = ?
    AND a.status = 'checked_in'
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$checkedin_today = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

// Get today's queue rows
$stmt = $conn->prepare("
  SELECT
    a.appointment_id,
    a.arrival_time,
    s.service_name,
    qc.category_name,
    q.queue_code,
    q.status AS queue_status,
    sc.counter_name
  FROM appointments a
  JOIN services s ON s.service_id = a.service_id
  JOIN queue_categories qc ON qc.category_id = a.category_id
  JOIN queue q ON q.appointment_id = a.appointment_id
  LEFT JOIN service_counters sc ON sc.counter_id = q.counter_id
  WHERE a.user_id = ?
    AND a.appointment_date = ?
  ORDER BY q.queue_code ASC
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$today_rows = $stmt->get_result();

// Get user name
$name = (string) ($_SESSION['name'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Basic page setup -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard — PhilHealth</title>

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Lucide icons -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Tailwind custom theme -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Plus Jakarta Sans', 'ui-sans-serif', 'system-ui']
          },
          colors: {
            brand: {
              900: '#1a4d2e',
              800: '#235f36',
              700: '#2d7a3a',
              600: '#3ea546',
              500: '#4caf50',
            }
          }
        }
      }
    }
  </script>

  <!-- Small custom style -->
  <style>
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(rgba(255, 255, 255, 0.14) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.14) 1px, transparent 1px);
      background-size: 52px 52px;
      -webkit-mask-image: radial-gradient(ellipse 80% 55% at 50% 0%, #000 40%, transparent 80%);
      mask-image: radial-gradient(ellipse 80% 55% at 50% 0%, #000 40%, transparent 80%);
      opacity: 0.35;
      pointer-events: none;
    }
  </style>
</head>

<body class="font-sans min-h-dvh bg-gradient-to-br from-lime-200 via-lime-400 to-green-600 text-slate-900 overflow-x-hidden">

  <!-- Top header -->
  <header class="sticky top-0 z-50 border-b border-white/30 bg-white/20 backdrop-blur-xl">
    <div class="mx-auto flex h-[62px] max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
      <div class="flex items-center gap-3 min-w-0">
        <img src="../logo/philhealth_Logo.png"
          alt="PhilHealth"
          class="h-9 w-9 rounded-xl bg-white p-1 shadow-sm ring-2 ring-white/80 object-contain" />

        <div class="min-w-0">
          <div class="text-[10px] font-extrabold tracking-wider uppercase text-brand-900/70">Dashboard</div>
          <div class="truncate text-sm font-extrabold tracking-tight text-brand-900">
            <?php echo e(strtoupper($name)); ?>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <div class="hidden sm:inline-flex items-center rounded-full border border-white/60 bg-white/50 px-4 py-1 text-xs font-semibold text-brand-900">
          <?php echo e(date('M d, Y')); ?>
        </div>

        <a href="logout.php"
          class="inline-flex items-center gap-2 rounded-xl bg-brand-900 px-4 py-2 text-sm font-extrabold text-white shadow-sm shadow-black/10 hover:bg-brand-700 active:translate-y-[1px] transition">
          <i data-lucide="log-out" class="h-4 w-4"></i>
          Logout
        </a>
      </div>
    </div>
  </header>

  <!-- Main content -->
  <main class="mx-auto max-w-6xl px-4 sm:px-6 py-6 sm:py-8">

    <!-- Summary cards -->
    <section class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="rounded-2xl border border-white/60 bg-white/90 backdrop-blur p-5 shadow-sm shadow-black/5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-[11px] font-extrabold tracking-wider uppercase text-slate-600">Upcoming</div>
            <div class="mt-1 text-5xl font-extrabold tracking-tight text-brand-900 leading-none">
              <?php echo (int) $upcoming_count; ?>
            </div>
            <div class="mt-2 text-xs text-slate-500">Booked + checked-in, not yet completed</div>
          </div>

          <div class="grid place-items-center h-11 w-11 rounded-xl bg-green-50 text-brand-700 ring-1 ring-green-100">
            <i data-lucide="calendar" class="h-5 w-5"></i>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-white/60 bg-white/90 backdrop-blur p-5 shadow-sm shadow-black/5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-[11px] font-extrabold tracking-wider uppercase text-slate-600">Checked-in Today</div>
            <div class="mt-1 text-5xl font-extrabold tracking-tight text-brand-900 leading-none">
              <?php echo (int) $checkedin_today; ?>
            </div>
            <div class="mt-2 text-xs text-slate-500">Queue number assigned after check-in</div>
          </div>

          <div class="grid place-items-center h-11 w-11 rounded-xl bg-amber-50 text-amber-700 ring-1 ring-amber-100">
            <i data-lucide="ticket" class="h-5 w-5"></i>
          </div>
        </div>
      </div>
    </section>

    <!-- Quick actions -->
    <section class="mt-5 rounded-2xl border border-white/60 bg-white/90 backdrop-blur p-5 shadow-sm shadow-black/5">
      <div>
        <div class="text-sm font-extrabold text-slate-900">Quick Actions</div>
        <div class="text-xs text-slate-500">Common tasks</div>
      </div>

      <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <a href="book_appointment.php"
          class="group flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50/70 p-4 hover:border-brand-500 hover:bg-green-50 transition">
          <div class="grid h-10 w-10 place-items-center rounded-xl bg-brand-900 text-white shadow-sm shadow-black/10">
            <i data-lucide="plus" class="h-5 w-5"></i>
          </div>
          <div class="min-w-0">
            <div class="text-sm font-extrabold text-slate-900">Book Appointment</div>
            <div class="text-xs text-slate-500">Reserve a date &amp; time</div>
          </div>
          <div class="ml-auto text-slate-400 group-hover:text-brand-700 transition">
            <i data-lucide="chevron-right" class="h-5 w-5"></i>
          </div>
        </a>

        <a href="my_appointments.php"
          class="group flex items-center gap-3 rounded-2xl border border-purple-200 bg-purple-50/70 p-4 hover:border-purple-400 hover:bg-purple-50 transition">
          <div class="grid h-10 w-10 place-items-center rounded-xl bg-purple-600 text-white shadow-sm shadow-black/10">
            <i data-lucide="list" class="h-5 w-5"></i>
          </div>
          <div class="min-w-0">
            <div class="text-sm font-extrabold text-slate-900">My Appointments</div>
            <div class="text-xs text-slate-500">Status and queue codes</div>
          </div>
          <div class="ml-auto text-slate-400 group-hover:text-purple-600 transition">
            <i data-lucide="chevron-right" class="h-5 w-5"></i>
          </div>
        </a>

        <a href="requirements.php"
          class="group flex items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50/70 p-4 hover:border-blue-400 hover:bg-blue-50 transition">
          <div class="grid h-10 w-10 place-items-center rounded-xl bg-blue-600 text-white shadow-sm shadow-black/10">
            <i data-lucide="file-text" class="h-5 w-5"></i>
          </div>
          <div class="min-w-0">
            <div class="text-sm font-extrabold text-slate-900">Service Requirements</div>
            <div class="text-xs text-slate-500">Documents per service</div>
          </div>
          <div class="ml-auto text-slate-400 group-hover:text-blue-600 transition">
            <i data-lucide="chevron-right" class="h-5 w-5"></i>
          </div>
        </a>
      </div>
    </section>

    <!-- Today's queue table -->
    <section class="mt-5 rounded-2xl border border-white/60 bg-white/90 backdrop-blur p-5 shadow-sm shadow-black/5">
      <div>
        <div class="text-sm font-extrabold text-slate-900">Today's Queue Status</div>
        <div class="text-xs text-slate-500">Refresh to update</div>
      </div>

      <?php if ($today_rows->num_rows === 0): ?>
        <div class="mt-4 flex gap-3 rounded-2xl border border-green-200 bg-green-50/70 p-4 text-slate-700">
          <div class="mt-0.5 text-brand-700">
            <i data-lucide="info" class="h-5 w-5"></i>
          </div>
          <div>
            <div class="font-extrabold text-slate-900">No queue entries for today.</div>
            <div class="mt-1 text-sm text-slate-600">
              If you have a booking, check in at the office kiosk to receive a queue number.
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="mt-4 overflow-x-auto">
          <table class="min-w-[860px] w-full border-separate border-spacing-0">
            <thead>
              <tr class="text-left">
                <th class="sticky left-0 bg-white/90 backdrop-blur border-b-2 border-green-100 px-3 py-2 text-[11px] font-extrabold tracking-wider uppercase text-slate-500">Queue Code</th>
                <th class="border-b-2 border-green-100 px-3 py-2 text-[11px] font-extrabold tracking-wider uppercase text-slate-500">Category</th>
                <th class="border-b-2 border-green-100 px-3 py-2 text-[11px] font-extrabold tracking-wider uppercase text-slate-500">Service</th>
                <th class="border-b-2 border-green-100 px-3 py-2 text-[11px] font-extrabold tracking-wider uppercase text-slate-500">Counter</th>
                <th class="border-b-2 border-green-100 px-3 py-2 text-[11px] font-extrabold tracking-wider uppercase text-slate-500">Status</th>
                <th class="border-b-2 border-green-100 px-3 py-2 text-[11px] font-extrabold tracking-wider uppercase text-slate-500">Arrival</th>
              </tr>
            </thead>

            <tbody class="text-sm text-slate-700">
              <?php while ($r = $today_rows->fetch_assoc()):
                $st = strtolower((string) ($r['queue_status'] ?? 'waiting'));
                $badge = "bg-yellow-50 text-amber-800 ring-1 ring-amber-200";

                if ($st === 'serving')   $badge = "bg-blue-50 text-blue-800 ring-1 ring-blue-200";
                if ($st === 'done')      $badge = "bg-green-50 text-green-800 ring-1 ring-green-200";
                if ($st === 'cancelled') $badge = "bg-red-50 text-red-800 ring-1 ring-red-200";
              ?>
                <tr class="hover:bg-green-50/40 transition">
                  <td class="sticky left-0 bg-white/90 backdrop-blur border-b border-green-50 px-3 py-2">
                    <span class="text-base font-extrabold text-brand-900"><?php echo e($r['queue_code']); ?></span>
                  </td>
                  <td class="border-b border-green-50 px-3 py-2"><?php echo e($r['category_name']); ?></td>
                  <td class="border-b border-green-50 px-3 py-2"><?php echo e($r['service_name']); ?></td>
                  <td class="border-b border-green-50 px-3 py-2"><?php echo e($r['counter_name'] ?? '—'); ?></td>
                  <td class="border-b border-green-50 px-3 py-2">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-extrabold <?php echo $badge; ?>">
                      <?php echo e(ucfirst($st)); ?>
                    </span>
                  </td>
                  <td class="border-b border-green-50 px-3 py-2">
                    <?php echo $r['arrival_time'] ? e(date('h:i A', strtotime($r['arrival_time']))) : '—'; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <!-- Welcome modal -->
  <div id="welcomeModal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

    <div class="relative w-full max-w-sm overflow-hidden rounded-3xl bg-white shadow-2xl">
      <div class="h-1.5 bg-gradient-to-r from-brand-900 via-brand-500 to-amber-400"></div>

      <div class="p-6">
        <div class="flex items-center gap-3">
          <div class="grid h-12 w-12 place-items-center rounded-2xl bg-green-50 text-brand-700 ring-1 ring-green-100">
            <i data-lucide="sparkles" class="h-6 w-6"></i>
          </div>
          <div>
            <div class="text-xl font-extrabold text-slate-900">Welcome back!</div>
            <div class="text-xs text-slate-500">Closes automatically in 3 seconds</div>
          </div>
        </div>

        <p class="mt-4 text-sm leading-6 text-slate-700">
          Hello <span class="font-extrabold text-brand-900"><?php echo e($name); ?></span>, you're now in your dashboard.
          Book appointments, check queue status, and view requirements here.
        </p>

        <button id="closeWelcomeModal"
          class="mt-5 w-full rounded-2xl bg-brand-900 px-4 py-3 text-sm font-extrabold text-white hover:bg-brand-700 active:translate-y-[1px] transition">
          Got it
        </button>
      </div>
    </div>
  </div>

  <!-- Chat widget -->
  <div class="fixed bottom-5 right-5 z-[150]">
    <!-- Chat box -->
    <div id="chat-window"
      class="hidden flex-col overflow-hidden bg-white shadow-2xl ring-1 ring-green-200 fixed inset-0 sm:inset-auto sm:bottom-[84px] sm:right-5 sm:h-[640px] sm:w-[400px] sm:rounded-3xl"
      role="dialog" aria-label="Sagip Assistant chat" aria-modal="true">

      <!-- Chat header -->
      <div class="flex items-center justify-between gap-3 bg-brand-900 px-4 py-3 text-white pt-[calc(12px+env(safe-area-inset-top))] sm:pt-3">
        <div class="flex items-center gap-3 min-w-0">
          <div class="grid h-9 w-9 place-items-center rounded-full bg-white/15 ring-1 ring-white/25 text-xs font-extrabold">
            SA
          </div>
          <div class="min-w-0">
            <div class="text-sm font-extrabold leading-tight">Sagip Assistant</div>
            <div class="text-xs text-white/70">Handa akong tumulong</div>
          </div>
        </div>

        <button id="chat-close-btn"
          class="inline-grid h-9 w-9 place-items-center rounded-xl bg-white/10 hover:bg-white/15 transition"
          aria-label="Close">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>

      <!-- Chat messages -->
      <div id="chat-messages" class="flex-1 overflow-y-auto bg-green-50/60 p-4 space-y-3"></div>

      <!-- Typing animation -->
      <div id="typing-box" class="hidden bg-green-50/60 px-4 pb-2">
        <div class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-2 ring-1 ring-green-200 shadow-sm">
          <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-bounce"></span>
          <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-bounce [animation-delay:120ms]"></span>
          <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-bounce [animation-delay:240ms]"></span>
        </div>
      </div>

      <!-- Quick replies -->
      <div id="quick-replies" class="hidden border-t border-green-200 bg-white">
        <div class="flex items-center justify-between px-4 pt-2 pb-1">
          <span class="text-[11px] font-extrabold tracking-wider uppercase text-slate-500">Quick options</span>
          <button id="quick-collapse" class="text-xs font-extrabold text-brand-700 hover:text-brand-900">Hide</button>
        </div>

        <div id="quick-replies-wrap" class="flex gap-2 overflow-x-auto px-4 pb-3 [scrollbar-width:none]"></div>
      </div>

      <!-- Chat input -->
      <div id="chat-input-area" class="hidden border-t border-green-200 bg-white p-3 pb-[calc(12px+env(safe-area-inset-bottom))] sm:pb-3">
        <form id="chat-form" class="flex items-center gap-2">
          <input id="chat-input" type="text" autocomplete="off"
            class="flex-1 rounded-2xl border border-green-200 bg-green-50 px-4 py-2 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-green-200/50"
            placeholder="Mag-type ng mensahe...">
          <button type="submit"
            class="grid h-10 w-10 place-items-center rounded-2xl bg-brand-900 text-white hover:bg-brand-700 active:translate-y-[1px] transition"
            aria-label="Send">
            <i data-lucide="send" class="h-4 w-4"></i>
          </button>
        </form>
      </div>
    </div>

    <!-- Open/close chat button -->
    <button id="chat-toggle-btn"
      class="grid h-14 w-14 place-items-center rounded-2xl bg-brand-900 text-white shadow-xl shadow-black/20 ring-2 ring-white/30 hover:bg-brand-700 active:translate-y-[1px] transition"
      aria-label="Open chat">
      <i id="chat-icon" data-lucide="message-circle" class="h-6 w-6"></i>
    </button>
  </div>

  <script>
    // Load icons
    lucide.createIcons();

    // Chat URLs
    const BASE_URL = '/philhealth_queue';
    const CHAT_HELPER_URL = `${BASE_URL}/user/chat_helper.php`;
    const CHAT_EVENT_URL = `${BASE_URL}/user/chat_event.php`;

    // =========================
    // WELCOME MODAL
    // =========================
    const showW = <?php echo $showWelcomeModal ? 'true' : 'false'; ?>;
    const wModal = document.getElementById('welcomeModal');

    function openW() {
      if (!wModal) return;
      wModal.classList.remove('hidden');
      wModal.classList.add('flex');
      wModal.setAttribute('aria-hidden', 'false');
      lucide.createIcons();
      setTimeout(closeW, 3000);
    }

    function closeW() {
      if (!wModal) return;
      wModal.classList.add('hidden');
      wModal.classList.remove('flex');
      wModal.setAttribute('aria-hidden', 'true');
    }

    if (showW) openW();

    document.getElementById('closeWelcomeModal')?.addEventListener('click', closeW);

    wModal?.addEventListener('click', e => {
      if (e.target === wModal) closeW();
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeW();
    });

    // =========================
    // CHAT VALUES
    // =========================
    let nodeId = 'root';
    let pollTimer = null;
    let rtBubble = null;
    let quickMode = 'pills';

    const chatWin = document.getElementById('chat-window');
    const chatIcon = document.getElementById('chat-icon');
    const msgs = document.getElementById('chat-messages');
    const qrBox = document.getElementById('quick-replies');
    const qrWrap = document.getElementById('quick-replies-wrap');
    const inputArea = document.getElementById('chat-input-area');
    const typBox = document.getElementById('typing-box');

    // Stop live queue checking
    function stopPoll() {
      if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
      }
      rtBubble = null;
    }

    // Ask queue update from server
    async function pollOnce() {
      const r = await fetch(CHAT_HELPER_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          message: "What is my queue number today?",
          meta: {
            source: 'poll',
            node_id: nodeId
          }
        })
      });

      if (!r.ok) throw new Error('fail');
      return r.json();
    }

    // Start live queue update
    function startPoll() {
      stopPoll();

      pollTimer = setInterval(async () => {
        try {
          if (chatWin.classList.contains('hidden')) return;

          const d = await pollOnce();

          if (d && (d.realtime || d.node_id === 'QUEUE_STATUS')) {
            if (rtBubble) {
              rtBubble.innerHTML = fmt(d.response || '...');
              msgs.scrollTop = msgs.scrollHeight;
            }
          } else {
            stopPoll();
          }
        } catch (e) {}
      }, 4000);
    }

    // Safe text
    function esc(s) {
      return String(s)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
    }

    // Format message text
    function fmt(t) {
      t = String(t || '').trim();
      if (!t) return '';

      const ls = t.split(/\n+/).map(s => s.trim()).filter(Boolean);

      if (ls.length >= 2 && ls.every(l => /^\d+\.\s+/.test(l))) {
        return `<ol class="list-decimal pl-5 space-y-1">${ls.map(l => `<li>${esc(l.replace(/^\d+\.\s+/, ''))}</li>`).join('')}</ol>`;
      }

      return esc(t).replace(/\n/g, '<br>');
    }

    // First chatbot message
    const greet =
      `Kumusta! Ako si Sagip Assistant. 🏥💬

Narito ako upang tumulong sa mga katanungan tungkol sa benepisyong pangkalusugan, membership, at kung paano gamitin ang sistema para sa appointment. ✅

Upang magpatuloy, pindutin ang "Magpatuloy". 👉
Kung nais mong tapusin ang usapan, pindutin ang "Tapusin". 👋`;

    // First buttons
    const initBtns = [{
        label: 'Magpatuloy',
        value: 'Magpatuloy',
        action: 'continue'
      },
      {
        label: 'Tapusin',
        value: 'Tapusin',
        action: 'end'
      }
    ];

    // Topic buttons
    const topicBtns = [{
        label: 'Benepisyong Pangkalusugan',
        value: 'Benepisyong Pangkalusugan',
        node_id: 'benefits'
      },
      {
        label: 'Impormasyon sa Membership',
        value: 'Impormasyon sa Membership',
        node_id: 'membership'
      },
      {
        label: 'Proseso ng Appointment',
        value: 'Proseso ng Pagkuha ng Appointment',
        node_id: 'appointment_process'
      },
      {
        label: 'Step-by-step',
        value: 'Step-by-step procedure',
        node_id: 'stepbystep'
      },
      {
        label: 'Main Menu',
        value: 'Menu',
        node_id: 'root'
      }
    ];

    // Add bot bubble
    function botBbl(t) {
      const el = document.createElement('div');
      el.className = 'max-w-[88%] sm:max-w-[86%] rounded-2xl rounded-bl-md bg-white ring-1 ring-green-200 px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm shadow-black/5';
      el.innerHTML = fmt(t);
      msgs.appendChild(el);
      msgs.scrollTop = msgs.scrollHeight;
      return el;
    }

    // Add user bubble
    function usrBbl(t) {
      const el = document.createElement('div');
      el.className = 'ml-auto max-w-[88%] sm:max-w-[86%] rounded-2xl rounded-br-md bg-brand-900 px-4 py-3 text-sm font-semibold leading-6 text-white shadow-sm shadow-black/10';
      el.textContent = t;
      msgs.appendChild(el);
      msgs.scrollTop = msgs.scrollHeight;
    }

    // Show or hide typing
    function setTyp(v) {
      if (v) {
        typBox.classList.remove('hidden');
      } else {
        typBox.classList.add('hidden');
      }

      if (v) msgs.scrollTop = msgs.scrollHeight;
    }

    // Change quick reply style
    function applyQuickMode(mode) {
      quickMode = mode;
      qrWrap.className = '';

      if (mode === 'stack') {
        qrWrap.className = 'flex flex-col gap-2 px-4 pb-3';
      } else {
        qrWrap.className = 'flex gap-2 overflow-x-auto px-4 pb-3 [scrollbar-width:none]';
      }
    }

    // Show quick reply buttons
    function renderBtns(btns) {
      qrWrap.innerHTML = '';

      if (!btns || !btns.length) {
        qrBox.classList.add('hidden');
        return;
      }

      btns.forEach(b => {
        const el = document.createElement('button');
        el.type = 'button';
        el.textContent = b.label;

        let base = 'transition ring-1 font-extrabold';
        let cls = '';

        if (quickMode === 'stack') {
          base += ' w-full text-left rounded-2xl px-4 py-3 text-sm';
          cls = 'bg-white text-slate-800 ring-green-200 hover:bg-green-50';

          if (b.action === 'continue') cls = 'bg-brand-900 text-white ring-brand-900/20 hover:bg-brand-700';
          if (b.action === 'end') cls = 'bg-amber-50 text-amber-800 ring-amber-200 hover:bg-amber-100';
        } else {
          base += ' shrink-0 rounded-full px-4 py-2 text-xs';
          cls = 'bg-green-50 text-slate-800 ring-green-200 hover:bg-green-100';

          if (b.action === 'continue') cls = 'bg-brand-900 text-white ring-brand-900/20 hover:bg-brand-700';
          if (b.action === 'end') cls = 'bg-amber-50 text-amber-800 ring-amber-200 hover:bg-amber-100';
        }

        el.className = base + ' ' + cls;

        el.addEventListener('click', () => {
          if (b.action === 'end') {
            closeChat();
            return;
          }

          if (b.action === 'continue') {
            applyQuickMode('stack');
            botBbl('Ano ang nais mong malaman?\nPumili ng paksa o mag-type ng inyong tanong.');
            renderBtns(topicBtns);
            inputArea.classList.remove('hidden');
            return;
          }

          inputArea.classList.remove('hidden');
          sendMsg(b.value, {
            source: 'button',
            node_id: b.node_id || nodeId
          });
        });

        qrWrap.appendChild(el);
      });

      qrBox.classList.remove('hidden');
    }

    // Hide or show quick replies
    const quickCollapseBtn = document.getElementById('quick-collapse');

    if (quickCollapseBtn) {
      quickCollapseBtn.addEventListener('click', () => {
        const hidden = qrWrap.classList.toggle('hidden');
        quickCollapseBtn.textContent = hidden ? 'Show' : 'Hide';
      });
    }

    // Save chat events
    async function logEvt(t, p = {}) {
      try {
        await fetch(CHAT_EVENT_URL, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            event_type: t,
            payload: p
          })
        });
      } catch (e) {}
    }

    // Send message to chatbot
    async function sendMsg(txt, meta = {}) {
      if (!txt) return;

      const inp = document.getElementById('chat-input');
      const sb = document.querySelector('[aria-label="Send"]');

      stopPoll();
      qrBox.classList.add('hidden');

      if (inp) inp.disabled = true;
      if (sb) sb.disabled = true;

      usrBbl(txt);
      setTyp(true);

      await logEvt('user_message', {
        text: txt,
        source: meta.source || 'typed',
        node_id: meta.node_id || null
      });

      try {
        const r = await fetch(CHAT_HELPER_URL, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            message: txt,
            meta
          })
        });

        if (!r.ok) throw new Error('net');
        const d = await r.json();

        setTyp(false);
        nodeId = d.node_id || nodeId;

        const bb = botBbl(d.response || 'No response.');
        inputArea.classList.remove('hidden');

        if (Array.isArray(d.quick_replies) && d.quick_replies.length) {
          applyQuickMode('stack');

          qrWrap.classList.remove('hidden');
          if (quickCollapseBtn) quickCollapseBtn.textContent = 'Hide';

          renderBtns(d.quick_replies.map(q => ({
            label: q.label || q.value,
            value: q.value || q.label,
            node_id: q.node_id || null
          })));
        } else {
          qrBox.classList.add('hidden');
        }

        if (d && (d.realtime || d.node_id === 'QUEUE_STATUS')) {
          rtBubble = bb;
          startPoll();
        }

        await logEvt('bot_response', {
          node_id: d.node_id || null
        });

      } catch (e) {
        setTyp(false);
        botBbl('Paumanhin, hindi ako makakonekta. Pakisubukang muli.');
      } finally {
        if (inp) {
          inp.disabled = false;
          inp.focus();
        }
        if (sb) sb.disabled = false;
        msgs.scrollTop = msgs.scrollHeight;
      }
    }

    // Send typed message
    document.getElementById('chat-form')?.addEventListener('submit', e => {
      e.preventDefault();
      const inp = document.getElementById('chat-input');
      const t = (inp.value || '').trim();
      inp.value = '';
      if (t) sendMsg(t, {
        source: 'typed'
      });
    });

    // Open chat
    function openChat() {
      stopPoll();
      nodeId = 'root';

      chatWin.classList.remove('hidden');
      chatWin.classList.add('flex');

      chatIcon.setAttribute('data-lucide', 'chevron-down');
      lucide.createIcons();

      msgs.innerHTML = '';
      setTyp(false);

      applyQuickMode('pills');
      botBbl(greet);
      renderBtns(initBtns);

      inputArea.classList.add('hidden');
    }

    // Close chat
    function closeChat() {
      stopPoll();
      nodeId = 'root';

      chatWin.classList.add('hidden');
      chatWin.classList.remove('flex');

      chatIcon.setAttribute('data-lucide', 'message-circle');
      lucide.createIcons();

      qrBox.classList.add('hidden');
      setTyp(false);
    }

    // Chat buttons
    document.getElementById('chat-toggle-btn')?.addEventListener('click', () => {
      const isOpen = !chatWin.classList.contains('hidden');
      isOpen ? closeChat() : openChat();
    });

    document.getElementById('chat-close-btn')?.addEventListener('click', closeChat);

    // Close chat on ESC
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        if (!chatWin.classList.contains('hidden')) closeChat();
      }
    });
  </script>
</body>

</html>