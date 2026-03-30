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

// Default messages
$error = "";
$success = "";
$loginUrl = "/philhealth_queue/user/login.php";

// Clean mobile number input
function normalize_mobile($raw)
{
  $raw = trim((string)$raw);

  if ($raw === "") {
    return "";
  }

  return preg_replace('/\D+/', '', $raw);
}

// Run when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();

  // Get form values
  $first_name  = trim($_POST['first_name'] ?? "");
  $middle_name = trim($_POST['middle_name'] ?? "");
  $last_name   = trim($_POST['last_name'] ?? "");
  $email       = trim($_POST['email'] ?? "");
  $mobile      = normalize_mobile($_POST['mobile_number'] ?? "");
  $password    = $_POST['password'] ?? "";
  $confirm     = $_POST['confirm_password'] ?? "";

  // Build full name
  $full_name = trim($first_name . " " . $middle_name . " " . $last_name);
  $full_name = preg_replace('/\s+/', ' ', $full_name);

  // Check input values
  if (
    $first_name === "" ||
    $middle_name === "" ||
    $last_name === "" ||
    $email === "" ||
    $password === "" ||
    $confirm === ""
  ) {
    $error = "All required fields must be filled in.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Invalid email format.";
  } elseif ($mobile !== "" && !preg_match('/^09\d{9}$/', $mobile)) {
    $error = "Mobile number must be 11 digits and start with 09.";
  } elseif (
    strlen($password) < 8 ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[^A-Za-z0-9]/', $password)
  ) {
    $error = "Password must be at least 8 characters and include uppercase, lowercase, number, and special character.";
  } elseif ($password !== $confirm) {
    $error = "Passwords do not match.";
  } else {
    // Check email domain for role
    $email_domain = strtolower(substr(strrchr($email, "@"), 1));
    $role = ($email_domain === "office.gov") ? "staff" : "user";

    // Set login page based on role
    $loginUrl = ($role === "staff")
      ? "/philhealth_queue/staff/login.php"
      : "/philhealth_queue/user/login.php";

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id, role FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $existing = $stmt->get_result();

    if ($existing && $existing->num_rows > 0) {
      $row = $existing->fetch_assoc();

      $existingLoginUrl = ($row['role'] === 'staff')
        ? "/philhealth_queue/staff/login.php"
        : "/philhealth_queue/user/login.php";

      $error = "Email is already registered. Please login. "
        . '<a class="font-semibold text-brand-700 hover:underline" href="' . e($existingLoginUrl) . '">Go to Login</a>';
    } else {
      // Hash password
      $hash = password_hash($password, PASSWORD_DEFAULT);

      // Save new user
      $stmt = $conn->prepare("
                INSERT INTO users (full_name, email, mobile_number, password_hash, role)
                VALUES (?, ?, ?, ?, ?)
            ");
      $stmt->bind_param("sssss", $full_name, $email, $mobile, $hash, $role);

      if ($stmt->execute()) {
        $success = ($role === "staff")
          ? "Staff account created successfully."
          : "Account created successfully.";

        // Clear old form values after success
        $_POST = [];
      } else {
        $error = "Registration failed. Please try again.";
      }
    }
  }
}

// Refill mobile input after form submit
$posted_mobile = trim($_POST['mobile_number'] ?? "");
$posted_digits = preg_replace('/\D+/', '', $posted_mobile);
$after09 = "";

if (preg_match('/^09(\d{0,9})$/', $posted_digits, $m)) {
  $after09 = $m[1];
} elseif (strlen($posted_digits) >= 2 && substr($posted_digits, 0, 2) === "09") {
  $after09 = substr($posted_digits, 2, 9);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Basic page setup -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Account</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Custom colors -->
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

<body class="min-h-screen flex items-center justify-center px-4 py-10 text-slate-900">
  <!-- Background -->
  <div class="fixed inset-0 -z-10 lime-bg"></div>
  <div class="fixed inset-0 -z-10 grid-overlay pointer-events-none"></div>

  <!-- Soft background shapes -->
  <div class="pointer-events-none fixed inset-0 overflow-hidden -z-10">
    <div class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-white blur-3xl opacity-25"></div>
    <div class="absolute -bottom-40 -left-40 h-96 w-96 rounded-full bg-brand-50 blur-3xl opacity-25"></div>
  </div>

  <!-- Main form box -->
  <main class="relative w-full max-w-md">
    <div class="bg-white/80 backdrop-blur rounded-3xl border border-white/50 shadow-2xl p-7 sm:p-8">

      <!-- Title -->
      <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Create your account</h1>
        <p class="mt-1 text-sm text-slate-700/80">Register to book appointments.</p>
      </div>

      <!-- Error message -->
      <?php if ($error): ?>
        <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          <?php echo $error; ?>
        </div>
      <?php endif; ?>

      <!-- Registration form -->
      <form method="POST" class="space-y-4" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <!-- Name fields -->
        <div class="space-y-2">

          <div>
            <label class="block text-sm font-medium text-slate-800 mb-1">
              First Name <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              name="first_name"
              placeholder="Juan"
              required
              value="<?php echo e($_POST['first_name'] ?? ''); ?>"
              class="w-full rounded-xl border border-gray-200 px-4 py-2 outline-none focus:border-green-700">
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-800 mb-1">
              Middle Name <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              name="middle_name"
              placeholder="Santos"
              value="<?php echo e($_POST['middle_name'] ?? ''); ?>"
              class="w-full rounded-xl border border-gray-200 px-4 py-2 outline-none focus:border-green-700">
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-800 mb-1">
              Last Name <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              name="last_name"
              placeholder="Dela Cruz"
              required
              value="<?php echo e($_POST['last_name'] ?? ''); ?>"
              class="w-full rounded-xl border border-gray-200 px-4 py-2 outline-none focus:border-green-700">
          </div>
        </div>

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-slate-800 mb-1">
            Email <span class="text-rose-600">*</span>
          </label>
          <input
            type="email"
            name="email"
            required
            autocomplete="email"
            placeholder="you@gmail.com"
            class="w-full rounded-2xl border border-white/60 bg-white/85 px-4 py-2 text-slate-900 placeholder-slate-500 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60"
            value="<?php echo e($_POST['email'] ?? ''); ?>">
        </div>

        <!-- Mobile number -->
        <div>
          <label class="block text-sm font-medium text-slate-800 mb-1">
            Mobile Number <span class="text-slate-600/70">(optional)</span>
          </label>

          <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-700 font-semibold select-none">09</span>

            <input
              type="text"
              id="mobile_number"
              inputmode="numeric"
              maxlength="9"
              placeholder="xxxxxxxxx"
              class="w-full rounded-2xl border border-white/60 bg-white/85 pl-12 pr-4 py-2 text-slate-900 placeholder-slate-500 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60"
              value="<?php echo e($after09); ?>">

            <input type="hidden" name="mobile_number" id="mobile_full" value="">
          </div>

          <p class="mt-1 text-xs text-slate-700/70">
            Used for kiosk check-in lookup. Format: 09 + 9 digits.
          </p>
        </div>

        <!-- Password fields -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          <!-- PASSWORD -->
          <div>
            <label class="block text-sm font-medium text-slate-800 mb-1">
              Password <span class="text-rose-600">*</span>
            </label>

            <div class="relative">
              <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                minlength="8"
                pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$"
                placeholder="Minimum 8 characters"
                class="w-full rounded-2xl border border-white/60 bg-white/85 px-4 py-2 pr-12 text-slate-900 placeholder-slate-500 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60">

              <button
                type="button"
                onclick="togglePassword('password', this)"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500">
                👁
              </button>
            </div>
          </div>


          <!-- CONFIRM PASSWORD -->
          <div>
            <label class="block text-sm font-medium text-slate-800 mb-1">
              Confirm <span class="text-rose-600">*</span>
            </label>

            <div class="relative">
              <input
                id="confirm_password"
                type="password"
                name="confirm_password"
                required
                autocomplete="new-password"
                placeholder="Repeat password"
                class="w-full rounded-2xl border border-white/60 bg-white/85 px-4 py-2 pr-12 text-slate-900 placeholder-slate-500 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60">

              <button
                type="button"
                onclick="togglePassword('confirm_password', this)"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500">
                👁
              </button>
            </div>
          </div>

        </div>

        <!-- Password note -->
        <p class="text-xs text-slate-700/70">
          Password must be at least 8 characters and include uppercase, lowercase, number, and special character.
        </p>

        <!-- Submit button -->
        <button
          type="submit"
          class="w-full rounded-2xl bg-brand-700 px-4 py-2 font-semibold text-white shadow-md transition hover:opacity-95 focus:outline-none focus:ring-4 focus:ring-white/60 active:translate-y-[1px] relative overflow-hidden group">
          <span
            class="pointer-events-none absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"
            style="background: radial-gradient(70% 60% at 50% 20%, rgba(228,181,25,.55) 0%, rgba(31,114,59,.25) 55%, transparent 80%);">
          </span>
          <span
            class="pointer-events-none absolute -top-10 left-0 h-24 w-full rotate-12 opacity-0 group-hover:opacity-100 transition duration-300"
            style="background: linear-gradient(90deg, transparent, rgba(228,181,25,.60), transparent);">
          </span>
          <span class="relative">Create account</span>
        </button>
      </form>

      <!-- Login link -->
      <p class="mt-6 text-sm text-slate-800/80">
        Already have an account?
        <a href="/philhealth_queue/user/login.php" class="font-semibold text-brand-700 hover:underline">Sign in</a>
      </p>
    </div>

    <!-- Small note -->
    <p class="mt-6 text-center text-xs text-slate-800/70">
      By creating an account, you agree to our <a href="terms.php" class="text-green-700 underline">Terms and Privacy Policy</a>
    </p>
  </main>

  <!-- Success modal -->
  <div id="successModal"
    class="fixed inset-0 z-50 hidden items-center justify-center p-4"
    aria-hidden="true">
    <div class="absolute inset-0 bg-black/40"></div>

    <div class="relative w-full max-w-sm rounded-3xl bg-white/90 backdrop-blur shadow-2xl border border-white/60">
      <div class="p-6">

        <div class="flex items-start gap-3">
          <div class="mt-0.5 h-10 w-10 rounded-2xl bg-brand-50 flex items-center justify-center border border-white/60">
            <svg viewBox="0 0 24 24" class="h-5 w-5 text-brand-700" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 6L9 17l-5-5" />
            </svg>
          </div>

          <div class="flex-1">
            <h2 class="text-lg font-semibold text-slate-900">Registered successfully</h2>
            <p class="mt-1 text-sm text-slate-700/80">
              <?php echo $success ? e($success) : ""; ?>
            </p>
            <p class="mt-2 text-xs text-slate-700/70">
              Continue to login to access your account.
            </p>
          </div>
        </div>

        <div class="mt-6 flex gap-3">
          <button
            type="button"
            id="closeModal"
            class="flex-1 rounded-2xl border border-white/60 bg-white/85 px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-white focus:outline-none focus:ring-4 focus:ring-white/60">
            Close
          </button>

          <a
            href="<?php echo e($loginUrl); ?>"
            class="flex-1 text-center rounded-2xl bg-brand-gold px-4 py-2.5 text-sm font-extrabold text-slate-900 hover:opacity-95 focus:outline-none focus:ring-4 focus:ring-white/60 border border-white/40">
            Go to Login
          </a>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Mobile number input
    const mobile = document.getElementById('mobile_number');
    const mobileFull = document.getElementById('mobile_full');

    function updateMobile() {
      mobile.value = (mobile.value || '').replace(/\D+/g, '').slice(0, 9);
      mobileFull.value = mobile.value.length ? ('09' + mobile.value) : '';
    }

    mobile.addEventListener('input', updateMobile);
    mobile.addEventListener('change', updateMobile);
    updateMobile();

    // Success modal
    const success = <?php echo $success ? 'true' : 'false'; ?>;
    const modal = document.getElementById('successModal');
    const closeBtn = document.getElementById('closeModal');

    function openModal() {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      modal.setAttribute('aria-hidden', 'true');
    }

    if (success) {
      openModal();
    }

    closeBtn?.addEventListener('click', closeModal);

    modal?.addEventListener('click', (e) => {
      if (e.target === modal || e.target === modal.firstElementChild) {
        closeModal();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeModal();
      }
    });
  </script>

  <script>
    function togglePassword(fieldId, btn) {

      const input = document.getElementById(fieldId);

      if (input.type === "password") {
        input.type = "text";
        btn.innerText = "🙈";
      } else {
        input.type = "password";
        btn.innerText = "👁";
      }

    }
  </script>
</body>

</html>