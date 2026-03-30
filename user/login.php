<?php
// Use user session
session_name('user_session');

// Set session cookie settings before session starts
session_set_cookie_params([
  'lifetime' => 0,       // Ends when browser closes
  'path' => '/',
  'domain' => '',
  'secure' => false,     // Change to true if using HTTPS
  'httponly' => true,    // Blocks JavaScript access
  'samesite' => 'Lax'
]);

// Start session
session_start();

// Load needed files
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/csrf.php";
require_once __DIR__ . "/../config/helpers.php";

// Set local time
date_default_timezone_set('Asia/Manila');

// If already logged in as user, go to dashboard
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'user') {
  header("Location: dashboard.php");
  exit();
}

// Default error message
$error = "";

// Run when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();

  // Get input values
  $email = trim($_POST['email'] ?? "");
  $password = $_POST['password'] ?? "";

  // Find account by email
  $stmt = $conn->prepare("
        SELECT user_id, full_name, email, password_hash, role
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $res = $stmt->get_result();

  // Check if account exists
  if ($res->num_rows === 1) {
    $user = $res->fetch_assoc();

    // Check password
    if (password_verify($password, $user['password_hash'])) {
      // Make new session ID after login
      session_regenerate_id(true);

      // Save user session
      $_SESSION['user_id'] = (int) $user['user_id'];
      $_SESSION['name'] = $user['full_name'];
      $_SESSION['role'] = $user['role'];

      // Allow only user account here
      if ($user['role'] === 'user') {
        header("Location: dashboard.php");
        exit();
      } else {
        $error = "This account is not a user account. Use the staff login page.";
        session_unset();
        session_destroy();
      }
    } else {
      $error = "Incorrect password.";
    }
  } else {
    $error = "No account found with that email.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Basic page setup -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Login</title>

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

  <!-- Main login box -->
  <main class="relative w-full max-w-md">
    <div class="bg-white/80 backdrop-blur rounded-3xl border border-white/50 shadow-2xl p-7 sm:p-8">

      <!-- Page title -->
      <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Welcome back</h1>
        <p class="mt-1 text-sm text-slate-700/80">Sign in to manage your bookings.</p>
      </div>

      <!-- Error message -->
      <?php if ($error): ?>
        <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <!-- Login form -->
      <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-slate-800 mb-1">Email</label>
          <input
            type="email"
            name="email"
            required
            autocomplete="email"
            class="w-full rounded-2xl border border-white/60 bg-white/85 px-4 py-3 text-slate-900 placeholder-slate-500 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60"
            placeholder="you@example.com"
            value="<?php echo e($_POST['email'] ?? ''); ?>">
        </div>

        <!-- Password -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="block text-sm font-medium text-slate-800">Password</label>
          </div>
          <input
            type="password"
            name="password"
            required
            autocomplete="current-password"
            class="w-full rounded-2xl border border-white/60 bg-white/85 px-4 py-3 text-slate-900 placeholder-slate-500 shadow-sm outline-none transition focus:border-brand-700 focus:ring-4 focus:ring-white/60"
            placeholder="Enter your password">
        </div>

        <!-- Sign in button -->
        <button
          type="submit"
          class="w-full rounded-2xl bg-brand-700 px-4 py-3 font-semibold text-white shadow-md transition hover:opacity-95 focus:outline-none focus:ring-4 focus:ring-white/60 active:translate-y-[1px] relative overflow-hidden group">
          <span
            class="pointer-events-none absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"
            style="background: radial-gradient(70% 60% at 50% 20%, rgba(228,181,25,.55) 0%, rgba(31,114,59,.25) 55%, transparent 80%);">
          </span>

          <span
            class="pointer-events-none absolute -top-10 left-0 h-24 w-full rotate-12 opacity-0 group-hover:opacity-100 transition duration-300"
            style="background: linear-gradient(90deg, transparent, rgba(228,181,25,.60), transparent);">
          </span>

          <span class="relative">Sign in</span>
        </button>
      </form>

      <!-- Divider -->
      <div class="mt-6 flex items-center gap-3">
        <div class="h-px flex-1 bg-white/60"></div>
        <span class="text-xs text-slate-700/70">or</span>
        <div class="h-px flex-1 bg-white/60"></div>
      </div>

      <!-- Register link -->
      <p class="mt-5 text-sm text-slate-800/80">
        Don’t have an account?
        <a href="register.php" class="font-semibold text-brand-700 hover:underline">Create one</a>
      </p>

      <!-- Back to home -->
      <div class="mt-4 text-center">
        <a href="../index.php"
          class="inline-flex items-center gap-1 rounded-2xl border border-white/60 bg-white/85 px-4 py-2 text-sm font-medium text-slate-800 shadow-sm transition hover:bg-white focus:outline-none focus:ring-4 focus:ring-white/60">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back to Home
        </a>
      </div>
    </div>

    <!-- Small note -->
    <p class="mt-6 text-center text-xs text-slate-800/70">
      By continuing, you agree to our terms and privacy policy.
    </p>
  </main>
</body>

</html>