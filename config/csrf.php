<?php
// config/csrf.php


/* =========================================================
   START SESSION
   CSRF token is stored inside the session.
   If session is not started yet, start it.
========================================================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   GENERATE CSRF TOKEN
   This creates a random security token and stores it
   in the user's session.
   The same token will be used when submitting forms.
========================================================= */
function csrf_token(): string
{

    // Create token only if it does not exist yet
    if (empty($_SESSION['csrf_token'])) {

        // Generate a secure random token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Return the token value
    return $_SESSION['csrf_token'];
}


/* =========================================================
   CREATE HIDDEN CSRF FIELD FOR FORMS
   This adds a hidden input field containing the token.
   Example output in HTML form:
   <input type="hidden" name="csrf_token" value="...">
========================================================= */
function csrf_field(): string
{

    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}


/* =========================================================
   VALIDATE CSRF TOKEN
   This checks if the submitted token matches the session.
   If the token is wrong, the request will be blocked.
========================================================= */
function csrf_validate(): void
{

    // Only check CSRF for POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    // Get token from submitted form
    $token = $_POST['csrf_token'] ?? '';

    // Compare form token with session token
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {

        // Block the request if token is invalid
        http_response_code(403);
        die("Invalid CSRF token.");
    }
}
