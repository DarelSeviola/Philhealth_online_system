<?php
// Set local time
date_default_timezone_set('Asia/Manila');

// Staff PIN for showing staff tools
const STAFF_PIN = '4829';

// Default values
$staffUnlocked = false;
$staffError = "";

// Clear staff access cookies
function clear_staff_unlock_cookies(): void
{
  setcookie('staff_unlocked', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'samesite' => 'Lax'
  ]);

  setcookie('staff_unlocked_until', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'samesite' => 'Lax'
  ]);
}

// Check PIN when staff submits the form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['staff_unlock'])) {
  $pin = trim((string)($_POST['staff_pin'] ?? ''));

  if (hash_equals(STAFF_PIN, $pin)) {
    $expiry = time() + 60; // 1 minute access

    setcookie('staff_unlocked', '1', [
      'expires' => $expiry,
      'path' => '/',
      'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
      'httponly' => true,
      'samesite' => 'Lax'
    ]);

    setcookie('staff_unlocked_until', (string)$expiry, [
      'expires' => $expiry,
      'path' => '/',
      'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
      'httponly' => false,
      'samesite' => 'Lax'
    ]);

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
  } else {
    $staffError = "Incorrect PIN.";
    clear_staff_unlock_cookies();
  }
}

// Check if staff tools are still unlocked
if (
  isset($_COOKIE['staff_unlocked'], $_COOKIE['staff_unlocked_until']) &&
  $_COOKIE['staff_unlocked'] === '1' &&
  ctype_digit((string)$_COOKIE['staff_unlocked_until']) &&
  (int)$_COOKIE['staff_unlocked_until'] > time()
) {
  $staffUnlocked = true;
} else {
  clear_staff_unlock_cookies();
}

// Load FAQ data
$faqs = [];
$faqFile = __DIR__ . '/user/faq_dataset.jsonl';

if (file_exists($faqFile)) {
  $handle = fopen($faqFile, 'r');

  if ($handle) {
    while (($line = fgets($handle)) !== false) {
      $data = json_decode($line, true);

      if (isset($data['messages']) && count($data['messages']) >= 2) {
        $question = $data['messages'][0]['content'] ?? '';
        $answer   = $data['messages'][1]['content'] ?? '';

        if ($question && $answer) {
          $faqs[] = [
            'question' => $question,
            'answer'   => $answer
          ];
        }
      }

      // Only show first 5 FAQs
      if (count($faqs) >= 5) break;
    }

    fclose($handle);
  }
}

// Load service categories for quick links
$serviceCategories = [];
$reqFile = __DIR__ . '/config/appointment_requirements.php';

if (file_exists($reqFile)) {
  $requirements = require $reqFile;

  foreach ($requirements as $key => $item) {
    $serviceCategories[] = [
      'key'   => $key,
      'label' => $item['label'] ?? ucfirst(str_replace('_', ' ', $key))
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PhilHealth Online Appointment</title>

  <!-- Tailwind and icons -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- Custom color setup -->
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
              600: "#3ea546",
              700: "#1F723B",
              800: "#235f36",
              900: "#1a4d2e",
              gold: "#E4B519",
              gold2: "#D29910"
            }
          }
        }
      }
    }
  </script>

  <!-- Small custom styles -->
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
      opacity: 0.35;
    }
  </style>
</head>

<body class="min-h-screen text-slate-900">

  <!-- Background -->
  <div class="fixed inset-0 -z-10 lime-bg"></div>
  <div class="fixed inset-0 -z-10 grid-overlay pointer-events-none"></div>

  <!-- Blur shapes -->
  <div class="pointer-events-none fixed inset-0 overflow-hidden -z-10">
    <div class="absolute -top-52 -right-60 h-[34rem] w-[34rem] rounded-full bg-white blur-3xl opacity-25"></div>
    <div class="absolute -bottom-52 -left-60 h-[34rem] w-[34rem] rounded-full bg-brand-50 blur-3xl opacity-25"></div>
  </div>

  <!-- Top header -->
  <header class="relative border-b border-white/40 bg-white/50 backdrop-blur">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-3">

      <div class="flex items-center gap-3 min-w-0">
        <div class="h-12 w-12 rounded-2xl bg-white border border-white/50 shadow-sm flex items-center justify-center overflow-hidden flex-none">
          <img src="./logo/philhealth_Logo.png" alt="PhilHealth Logo" class="h-10 w-10 object-contain">
        </div>

        <div class="min-w-0">
          <div class="text-sm sm:text-base font-semibold leading-tight truncate">
            PhilHealth Online Appointment
          </div>
          <div class="text-xs text-slate-700/80 flex items-center gap-2">
            <span>Book</span><span class="text-slate-700/40">•</span>
            <span>Check-in</span><span class="text-slate-700/40">•</span>
            <span>Queue Number</span>
          </div>
        </div>
      </div>

      <div class="hidden sm:block text-sm text-slate-700/90">
        <?php echo date("l, F d, Y"); ?>
      </div>
    </div>
  </header>

  <main class="relative">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-6">

      <!-- Main hero section -->
      <section class="rounded-3xl border border-white/40 bg-white/75 backdrop-blur shadow-sm overflow-hidden">
        <div class="p-5 sm:p-8">

          <div class="inline-flex items-center gap-2 rounded-full border border-white/50 bg-white/80 px-3 py-1 text-xs font-semibold text-slate-700">
            <span class="h-2 w-2 rounded-full bg-brand-700"></span>
            ONLINE BOOKING AVAILABLE
          </div>

          <h1 class="mt-4 text-2xl sm:text-4xl font-bold tracking-tight leading-tight text-slate-900">
            Skip the line. Book your<br class="hidden sm:block">
            <span class="text-brand-700">Appointment</span> Online.
          </h1>

          <!-- Login boxes -->
          <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- User box -->
            <div class="rounded-2xl border border-white/50 bg-white/85 p-5 shadow-sm">
              <div class="flex items-center justify-between gap-3">
                <div class="text-sm sm:text-base font-semibold">User Login</div>
                <i data-lucide="log-in" class="h-4 w-4 text-slate-600"></i>
              </div>

              <div class="mt-1 text-sm text-slate-700/80">Create an account / Login</div>

              <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="user/register.php"
                  class="rounded-xl bg-brand-700 px-4 py-3 text-sm font-semibold text-white text-center hover:opacity-95 focus:outline-none focus:ring-4 focus:ring-white/60">
                  Create account
                </a>

                <a href="user/login.php"
                  class="rounded-xl border-2 border-black bg-white/90 px-4 py-3 text-sm font-semibold text-slate-900 text-center hover:bg-white hover:shadow-md focus:outline-none focus:ring-4 focus:ring-black/20 transition">
                  Login
                </a>
              </div>

              <?php if (!empty($serviceCategories)): ?>
                <div class="mt-4 rounded-xl border border-white/50 bg-white/70 p-4">
                  <div class="text-xs font-semibold text-slate-700/70 uppercase tracking-wider">Services</div>

                  <div class="mt-2 flex flex-wrap gap-2">
                    <?php foreach (array_slice($serviceCategories, 0, 6) as $service): ?>
                      <a class="text-xs font-semibold rounded-full border border-white/60 bg-white/90 px-3 py-1 hover:bg-white"
                        href="user/requirements.php?service=<?php echo urlencode($service['key']); ?>">
                        <?php echo htmlspecialchars($service['label']); ?>
                      </a>
                    <?php endforeach; ?>
                  </div>

                  <div class="mt-2 text-xs text-slate-700/70">Open a service to view requirements.</div>
                </div>
              <?php endif; ?>
            </div>

            <!-- Staff box -->
            <div class="rounded-2xl border border-white/50 bg-white/85 p-5 shadow-sm">
              <div class="flex items-center justify-between gap-3">
                <div class="text-sm sm:text-base font-semibold">Admin / Staff Access</div>
                <i data-lucide="shield" class="h-4 w-4 text-slate-600"></i>
              </div>

              <?php if (!$staffUnlocked): ?>
                <div class="mt-2 text-sm text-slate-700/80">Enter PIN to unlock staff tools (1 minute).</div>

                <button type="button" onclick="openStaffPin()"
                  class="mt-4 w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-white/60">
                  Enter PIN
                </button>

                <?php if (!empty($staffError)): ?>
                  <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <?php echo htmlspecialchars($staffError, ENT_QUOTES, 'UTF-8'); ?>
                  </div>
                <?php endif; ?>

              <?php else: ?>
                <div class="mt-2 rounded-xl border border-white/50 bg-white/75 px-4 py-3">
                  <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                      <i data-lucide="unlock" class="h-4 w-4 text-brand-700"></i>
                      Staff tools unlocked
                    </div>

                    <button type="button"
                      class="text-xs font-semibold text-slate-800 underline underline-offset-4 hover:text-slate-950"
                      onclick="hideStaffTools()">
                      Hide
                    </button>
                  </div>

                  <div class="mt-1 text-xs text-slate-700/80">Visible for 1 minute only.</div>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <a href="staff/login.php"
                    class="rounded-xl border-2 border-black bg-white/90 px-4 py-3 hover:bg-white hover:shadow-md focus:outline-none focus:ring-4 focus:ring-black/20 transition">
                    <div class="text-sm font-semibold flex items-center gap-2">
                      <i data-lucide="users" class="h-4 w-4 text-brand-700"></i>
                      Staff Login
                    </div>
                    <div class="text-xs text-slate-700/80 mt-1">Serve and manage queue</div>
                  </a>

                  <a href="kiosk/index.php"
                    class="rounded-xl border-2 border-black bg-white/90 px-4 py-3 hover:bg-white hover:shadow-md focus:outline-none focus:ring-4 focus:ring-black/20 transition">
                    <div class="text-sm font-semibold flex items-center gap-2">
                      <i data-lucide="monitor" class="h-4 w-4 text-brand-gold2"></i>
                      Kiosk Terminal
                    </div>
                    <div class="text-xs text-slate-700/80 mt-1">Check-in & walk-in registration</div>
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Walk-in note -->
          <div class="mt-5 rounded-2xl border border-white/50 bg-white/70 px-5 py-4 flex items-start gap-3">
            <i data-lucide="user" class="h-5 w-5 text-brand-gold2 mt-0.5"></i>
            <div class="text-sm text-slate-800">
              <span class="font-semibold">Walk-in:</span>
              No appointment? You can still register at the office kiosk. Bring a valid ID.
              <div class="guide-message">
                <p>
                  <span class="font-semibold">Note:</span>
                  Please follow the process below to book your appointment, check in at the kiosk, receive your queue number, and wait to be called.
                </p>
              </div>
            </div>
          </div>

          <!-- Steps -->
          <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="rounded-2xl border border-white/60 bg-white/85 px-5 py-4">
              <div class="text-xs font-semibold text-slate-700/70 uppercase tracking-wider">Step 1</div>
              <div class="mt-1 text-sm font-semibold flex items-center gap-2">
                <i data-lucide="calendar" class="h-4 w-4 text-brand-700"></i> Book online
              </div>
              <div class="mt-1 text-sm text-slate-700/80">Choose service and time.</div>
            </div>

            <div class="rounded-2xl border border-white/60 bg-white/85 px-5 py-4">
              <div class="text-xs font-semibold text-slate-700/70 uppercase tracking-wider">Step 2</div>
              <div class="mt-1 text-sm font-semibold flex items-center gap-2">
                <i data-lucide="check-circle" class="h-4 w-4 text-brand-700"></i> Check in
              </div>
              <div class="mt-1 text-sm text-slate-700/80">Verify at kiosk upon arrival.</div>
            </div>

            <div class="rounded-2xl border border-white/60 bg-white/85 px-5 py-4">
              <div class="text-xs font-semibold text-slate-700/70 uppercase tracking-wider">Step 3</div>
              <div class="mt-1 text-sm font-semibold flex items-center gap-2">
                <i data-lucide="hash" class="h-4 w-4 text-brand-700"></i> Get queue number
              </div>
              <div class="mt-1 text-sm text-slate-700/80">Receive your ticket/number.</div>
            </div>

            <div class="rounded-2xl border border-white/60 bg-white/85 px-5 py-4">
              <div class="text-xs font-semibold text-slate-700/70 uppercase tracking-wider">Step 4</div>
              <div class="mt-1 text-sm font-semibold flex items-center gap-2">
                <i data-lucide="bell" class="h-4 w-4 text-brand-700"></i> Wait to be called
              </div>
              <div class="mt-1 text-sm text-slate-700/80">Proceed when your number is shown.</div>
            </div>
          </div>
        </div>
      </section>

      <!-- Requirements section -->
      <section class="rounded-3xl border border-white/40 bg-white/75 backdrop-blur shadow-sm p-6 sm:p-8">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-lg font-semibold tracking-tight">What to Prepare</h2>
          <div class="text-xs text-slate-700/70">Before visiting the office</div>
        </div>

        <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="rounded-2xl border border-white/60 bg-white/85 p-5">
            <div class="text-sm font-semibold flex items-center gap-2">
              <i data-lucide="ticket" class="h-4 w-4 text-brand-gold2"></i> Reference Code
            </div>
            <div class="mt-1 text-sm text-slate-700/80">
              If you booked online, keep your appointment reference ready.
            </div>
          </div>

          <div class="rounded-2xl border border-white/60 bg-white/85 p-5">
            <div class="text-sm font-semibold flex items-center gap-2">
              <i data-lucide="phone" class="h-4 w-4 text-brand-gold2"></i> Mobile Number
            </div>
            <div class="mt-1 text-sm text-slate-700/80">
              Registered mobile helps staff find your booking faster.
            </div>
          </div>

          <div class="rounded-2xl border border-white/60 bg-white/85 p-5">
            <div class="text-sm font-semibold flex items-center gap-2">
              <i data-lucide="file-text" class="h-4 w-4 text-brand-gold2"></i> Valid ID & Documents
            </div>
            <div class="mt-1 text-sm text-slate-700/80">
              Requirements vary by service. Check the service requirements page.
            </div>
          </div>
        </div>

        <?php if (!empty($serviceCategories)): ?>
          <div class="mt-6 rounded-2xl border border-white/50 bg-white/70 p-5">
            <div class="text-sm font-semibold">Quick access to requirements</div>

            <div class="mt-2 flex flex-wrap gap-2">
              <?php foreach ($serviceCategories as $service): ?>
                <a href="user/requirements.php?service=<?php echo urlencode($service['key']); ?>"
                  class="text-xs font-semibold rounded-full border border-white/60 bg-white/90 px-3 py-1 hover:bg-white">
                  <?php echo htmlspecialchars($service['label']); ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <!-- FAQ section -->
      <section class="rounded-3xl border border-white/40 bg-white/75 backdrop-blur shadow-sm p-6 sm:p-8">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-lg font-semibold tracking-tight">Common Asked Questions</h2>
          <div class="text-xs text-slate-700/70">FAQs</div>
        </div>

        <div class="mt-5 space-y-3">
          <?php if (empty($faqs)): ?>
            <div class="rounded-2xl border border-white/60 bg-white/85 p-5">
              <div class="text-sm font-semibold">Do I really need an account to book?</div>
              <div class="mt-1 text-sm text-slate-700/80">
                Yes. An account lets you manage your appointment, view your reference code, and keeps your info secure.
              </div>
            </div>

            <div class="rounded-2xl border border-white/60 bg-white/85 p-5">
              <div class="text-sm font-semibold">Can I check in without a reference code?</div>
              <div class="mt-1 text-sm text-slate-700/80">
                If your mobile number is registered, staff may look up your appointment using it.
              </div>
            </div>

            <div class="rounded-2xl border border-white/60 bg-white/85 p-5">
              <div class="text-sm font-semibold">What happens after I check in?</div>
              <div class="mt-1 text-sm text-slate-700/80">
                You will receive a queue number. Please wait for your number to be called.
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($faqs as $faq): ?>
              <div class="rounded-2xl border border-white/60 bg-white/85 p-5">
                <div class="text-sm font-semibold"><?php echo htmlspecialchars($faq['question']); ?></div>
                <div class="mt-1 text-sm text-slate-700/80"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>

  <!-- Footer -->
  <footer class="relative border-t border-white/40 bg-white/50 backdrop-blur">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-slate-800/80">
      <div>PhilHealth Queue System • <?php echo date("Y"); ?></div>
      <div>Citizen-friendly booking and check-in.</div>
    </div>
  </footer>

  <!-- Staff PIN modal -->
  <div id="staffModal" class="fixed inset-0 hidden items-center justify-center bg-black/40 p-4 z-[60]">
    <div class="w-full max-w-sm rounded-3xl bg-white border border-white/60 shadow-2xl p-6">

      <div class="flex items-start justify-between gap-4">
        <div>
          <h3 class="text-lg font-semibold text-slate-900">Staff access</h3>
          <p class="mt-1 text-sm text-slate-700/80">Enter PIN to show staff tools for 1 minute.</p>
        </div>

        <button class="text-slate-600 hover:text-slate-900" type="button" onclick="closeStaffPin()">✕</button>
      </div>

      <?php if (!empty($staffError)): ?>
        <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          <?php echo htmlspecialchars($staffError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="mt-4 space-y-3">
        <input type="hidden" name="staff_unlock" value="1">

        <input type="password"
          name="staff_pin"
          inputmode="numeric"
          maxlength="10"
          placeholder="Enter PIN"
          class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 placeholder-slate-400 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-brand-100"
          required>

        <button type="submit"
          class="w-full rounded-2xl bg-slate-900 px-4 py-3 font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 active:translate-y-[1px]">
          Unlock
        </button>
      </form>

      <p class="mt-3 text-xs text-slate-600">
        This PIN only shows staff tools on the homepage. Staff still needs their account to log in.
      </p>
    </div>
  </div>

  <!-- Chatbot -->
  <div class="fixed bottom-5 right-5 z-50">

    <!-- Chat panel -->
    <div id="sa-chat-panel"
      class="hidden fixed inset-0 sm:inset-auto sm:top-auto sm:bottom-[84px] sm:right-5 sm:w-[400px] sm:max-h-[85vh] sm:rounded-3xl bg-white shadow-2xl ring-1 ring-green-200 overflow-hidden flex-col"
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

        <button id="sa-chat-close" type="button"
          class="inline-grid h-9 w-9 place-items-center rounded-xl bg-white/10 hover:bg-white/15 transition"
          aria-label="Close chat">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>

      <!-- Chat messages -->
      <div id="sa-chat-messages" class="flex-1 overflow-y-auto bg-green-50/60 p-4 space-y-3"></div>

      <!-- Typing display -->
      <div id="sa-typing" class="hidden bg-green-50/60 px-4 pb-2">
        <div class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-2 ring-1 ring-green-200 shadow-sm">
          <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-bounce"></span>
          <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-bounce [animation-delay:120ms]"></span>
          <span class="h-1.5 w-1.5 rounded-full bg-brand-500 animate-bounce [animation-delay:240ms]"></span>
        </div>
      </div>

      <!-- Quick reply buttons -->
      <div id="sa-quick" class="hidden border-t border-green-200 bg-white">
        <div class="flex items-center justify-between px-4 pt-2 pb-1">
          <span class="text-[11px] font-extrabold tracking-wider uppercase text-slate-500">Quick options</span>
          <button id="sa-quick-toggle" type="button" class="text-xs font-extrabold text-brand-700 hover:text-brand-900">
            Hide
          </button>
        </div>

        <div id="sa-quick-wrap" class="flex gap-2 overflow-x-auto px-4 pb-3 [scrollbar-width:none]"></div>
      </div>

      <!-- Chat input -->
      <div id="sa-input-area"
        class="hidden border-t border-green-200 bg-white p-3 pb-[calc(12px+env(safe-area-inset-bottom))] sm:pb-3">
        <form id="sa-form" class="flex items-center gap-2">
          <input id="sa-input"
            type="text"
            autocomplete="off"
            class="flex-1 rounded-2xl border border-green-200 bg-green-50 px-4 py-2 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-green-200/50"
            placeholder="Mag-type ng mensahe...">

          <button id="sa-send" type="submit"
            class="grid h-10 w-10 place-items-center rounded-2xl bg-brand-900 text-white hover:bg-brand-700 active:translate-y-[1px] transition"
            aria-label="Send">
            <i data-lucide="send" class="h-4 w-4"></i>
          </button>
        </form>
      </div>
    </div>

    <!-- Floating open button -->
    <button id="sa-chat-open" type="button"
      class="grid h-14 w-14 place-items-center rounded-2xl bg-brand-900 text-white shadow-xl shadow-black/20 ring-2 ring-white/30 hover:bg-brand-700 active:translate-y-[1px] transition"
      aria-label="Open chat">
      <i id="sa-chat-icon" data-lucide="message-circle" class="h-6 w-6"></i>
    </button>
  </div>

  <script>
    // Load icons
    lucide.createIcons();

    // =========================
    // STAFF PIN MODAL
    // =========================
    const modal = document.getElementById('staffModal');

    function openStaffPin() {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeStaffPin() {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    <?php if (!empty($staffError)): ?>
      openStaffPin();
    <?php endif; ?>

    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeStaffPin();
    });

    // Hide staff tools manually
    function hideStaffTools() {
      document.cookie = 'staff_unlocked=; Max-Age=0; path=/; samesite=Lax';
      document.cookie = 'staff_unlocked_until=; Max-Age=0; path=/; samesite=Lax';
      location.replace(location.pathname);
    }

    // Auto-hide staff tools when time ends
    (function autoHideAfterExpiry() {
      function getCookie(name) {
        const match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return match ? match.pop() : '';
      }

      const until = parseInt(getCookie('staff_unlocked_until'), 10);
      if (!Number.isFinite(until)) return;

      const msLeft = (until * 1000) - Date.now();
      if (msLeft <= 0) {
        location.replace(location.pathname);
        return;
      }

      setTimeout(() => {
        document.cookie = 'staff_unlocked=; Max-Age=0; path=/; samesite=Lax';
        document.cookie = 'staff_unlocked_until=; Max-Age=0; path=/; samesite=Lax';
        location.replace(location.pathname);
      }, msLeft + 200);
    })();

    // =========================
    // CHATBOT SETTINGS
    // =========================
    const BASE_URL = '/philhealth_queue';
    const CHAT_HELPER_URL = `${BASE_URL}/user/chat_helper.php`;
    const CHAT_EVENT_URL = `${BASE_URL}/user/chat_event.php`;

    let saNodeId = 'root';
    let saQuickMode = 'pills';

    const saPanel = document.getElementById('sa-chat-panel');
    const saOpenBtn = document.getElementById('sa-chat-open');
    const saCloseBtn = document.getElementById('sa-chat-close');
    const saIcon = document.getElementById('sa-chat-icon');
    const saMsgs = document.getElementById('sa-chat-messages');
    const saTyping = document.getElementById('sa-typing');
    const saQuickBox = document.getElementById('sa-quick');
    const saQuickWrap = document.getElementById('sa-quick-wrap');
    const saQuickTog = document.getElementById('sa-quick-toggle');
    const saInputArea = document.getElementById('sa-input-area');
    const saForm = document.getElementById('sa-form');
    const saInput = document.getElementById('sa-input');
    const saSendBtn = document.getElementById('sa-send');

    // First chatbot message
    const saGreet = `Kumusta! Ako si Sagip Assistant.🏥💬

Narito ako upang tumulong sa mga katanungan tungkol sa benepisyong pangkalusugan, membership, at kung paano gamitin ang sistema para sa appointment.✅

Upang magpatuloy, pindutin ang "Magpatuloy".👉
Kung nais mong tapusin ang usapan, pindutin ang "Tapusin".👋`;

    // First quick reply buttons
    const saInitBtns = [{
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
    const saTopicBtns = [{
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

    // Escape special HTML symbols
    function saEsc(text) {
      return String(text)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
    }

    // Format message text
    function saFmt(text) {
      text = String(text || '').trim();
      if (!text) return '';

      const lines = text.split(/\n+/).map(s => s.trim()).filter(Boolean);

      if (lines.length >= 2 && lines.every(line => /^\d+\.\s+/.test(line))) {
        return `<ol class="list-decimal pl-5 space-y-1">${
          lines.map(line => `<li>${saEsc(line.replace(/^\d+\.\s+/, ''))}</li>`).join('')
        }</ol>`;
      }

      return saEsc(text).replace(/\n/g, '<br>');
    }

    // Show or hide typing animation
    function saSetTyping(on) {
      saTyping.classList.toggle('hidden', !on);
      if (on) saMsgs.scrollTop = saMsgs.scrollHeight;
    }

    // Add bot message
    function saBot(text) {
      const row = document.createElement('div');
      row.className = 'flex justify-start';

      const bubble = document.createElement('div');
      bubble.className = 'max-w-[88%] sm:max-w-[86%] rounded-2xl rounded-bl-md bg-white ring-1 ring-green-200 px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm shadow-black/5';
      bubble.innerHTML = saFmt(text);

      row.appendChild(bubble);
      saMsgs.appendChild(row);
      saMsgs.scrollTop = saMsgs.scrollHeight;
    }

    // Add user message
    function saUser(text) {
      const row = document.createElement('div');
      row.className = 'flex justify-end';

      const bubble = document.createElement('div');
      bubble.className = 'ml-auto max-w-[88%] sm:max-w-[86%] rounded-2xl rounded-br-md bg-brand-900 px-4 py-3 text-sm font-semibold leading-6 text-white shadow-sm shadow-black/10 break-words';
      bubble.textContent = text;

      row.appendChild(bubble);
      saMsgs.appendChild(row);
      saMsgs.scrollTop = saMsgs.scrollHeight;
    }

    function saShowInput() {
      saInputArea.classList.remove('hidden');
    }

    function saHideQuick() {
      saQuickBox.classList.add('hidden');
    }

    // Change button style mode
    function saApplyQuickMode(mode) {
      saQuickMode = mode;
      saQuickWrap.className = '';

      if (mode === 'stack') {
        saQuickWrap.className = 'flex flex-col gap-2 px-4 pb-3';
      } else {
        saQuickWrap.className = 'flex gap-2 overflow-x-auto px-4 pb-3 [scrollbar-width:none]';
      }
    }

    // Show quick reply buttons
    function saRenderButtons(buttons) {
      saQuickWrap.innerHTML = '';

      if (!buttons || !buttons.length) {
        saQuickBox.classList.add('hidden');
        return;
      }

      buttons.forEach(btn => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = btn.label;

        let base = 'transition ring-1 font-extrabold';
        let style = '';

        if (saQuickMode === 'stack') {
          base += ' w-full text-left rounded-2xl px-4 py-3 text-sm';
          style = 'bg-white text-slate-800 ring-green-200 hover:bg-green-50';

          if (btn.action === 'continue') style = 'bg-brand-900 text-white ring-brand-900/20 hover:bg-brand-700';
          if (btn.action === 'end') style = 'bg-amber-50 text-amber-800 ring-amber-200 hover:bg-amber-100';
        } else {
          base += ' shrink-0 rounded-full px-4 py-2 text-xs';
          style = 'bg-green-50 text-slate-800 ring-green-200 hover:bg-green-100';

          if (btn.action === 'continue') style = 'bg-brand-900 text-white ring-brand-900/20 hover:bg-brand-700';
          if (btn.action === 'end') style = 'bg-amber-50 text-amber-800 ring-amber-200 hover:bg-amber-100';
        }

        button.className = base + ' ' + style;

        button.addEventListener('click', () => {
          if (btn.action === 'end') {
            saClose();
            return;
          }

          if (btn.action === 'continue') {
            saApplyQuickMode('stack');
            saBot("Ano ang nais mong malaman?\nPumili ng paksa o mag-type ng inyong tanong.");
            saRenderButtons(saTopicBtns);
            saShowInput();
            return;
          }

          saShowInput();
          saSend(btn.value, {
            source: 'button',
            node_id: btn.node_id || saNodeId
          });
        });

        saQuickWrap.appendChild(button);
      });

      saQuickBox.classList.remove('hidden');
      saQuickWrap.classList.remove('hidden');
      if (saQuickTog) saQuickTog.textContent = 'Hide';
    }

    // Show or hide quick reply buttons
    saQuickTog?.addEventListener('click', () => {
      const willHide = !saQuickWrap.classList.contains('hidden');
      saQuickWrap.classList.toggle('hidden', willHide);
      saQuickTog.textContent = willHide ? 'Show' : 'Hide';
    });

    // Save chatbot events
    async function saLog(eventType, payload = {}) {
      try {
        await fetch(CHAT_EVENT_URL, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            event_type: eventType,
            payload
          })
        });
      } catch (e) {}
    }

    // Send message to chatbot
    async function saSend(text, meta = {}) {
      const msg = String(text || '').trim();
      if (!msg) return;

      saHideQuick();
      saInput.disabled = true;
      saSendBtn.disabled = true;

      saUser(msg);
      saSetTyping(true);

      await saLog('user_message', {
        text: msg,
        source: meta.source || 'typed',
        node_id: meta.node_id || null
      });

      try {
        const response = await fetch(CHAT_HELPER_URL, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            message: msg,
            meta
          })
        });

        if (!response.ok) throw new Error('net');

        const data = await response.json();

        saSetTyping(false);
        saNodeId = data.node_id || saNodeId;
        saBot(data.response || 'No response.');
        saShowInput();

        if (Array.isArray(data.quick_replies) && data.quick_replies.length) {
          if (saQuickMode !== 'stack') saApplyQuickMode('stack');

          const mapped = data.quick_replies.map(item => ({
            label: item.label || item.value,
            value: item.value || item.label,
            node_id: item.node_id || null
          }));

          saRenderButtons(mapped);
        } else {
          saHideQuick();
        }

        await saLog('bot_response', {
          node_id: data.node_id || null
        });
      } catch (e) {
        saSetTyping(false);
        saBot("Paumanhin, hindi ako makakonekta sa server. Pakisubukang muli.");
      } finally {
        saInput.disabled = false;
        saSendBtn.disabled = false;
        saInput.focus();
        saMsgs.scrollTop = saMsgs.scrollHeight;
      }
    }

    // Open chatbot
    function saOpen() {
      saNodeId = 'root';

      saPanel.classList.remove('hidden');
      saPanel.classList.add('flex');

      saIcon.setAttribute('data-lucide', 'chevron-down');
      lucide.createIcons();

      saMsgs.innerHTML = '';
      saSetTyping(false);

      saApplyQuickMode('pills');
      saBot(saGreet);
      saRenderButtons(saInitBtns);

      saInputArea.classList.add('hidden');
    }

    // Close chatbot
    function saClose() {
      saNodeId = 'root';

      saPanel.classList.add('hidden');
      saPanel.classList.remove('flex');

      saIcon.setAttribute('data-lucide', 'message-circle');
      lucide.createIcons();

      saHideQuick();
      saSetTyping(false);
    }

    // Open or close chat when button is clicked
    saOpenBtn.addEventListener('click', () => {
      const isOpen = !saPanel.classList.contains('hidden');
      isOpen ? saClose() : saOpen();
    });

    // Close button
    saCloseBtn.addEventListener('click', saClose);

    // Send typed message
    saForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const text = saInput.value.trim();
      saInput.value = '';
      if (text) saSend(text, {
        source: 'typed'
      });
    });

    // Close chat on ESC key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !saPanel.classList.contains('hidden')) {
        saClose();
      }
    });
  </script>
</body>

</html>