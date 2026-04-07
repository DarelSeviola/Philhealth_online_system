<?php
// Start staff session
session_name('staff_session');
session_start();

// Clear all session data
$_SESSION = [];

// Destroy session
session_destroy();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page
header("Location: login.php");
exit();
