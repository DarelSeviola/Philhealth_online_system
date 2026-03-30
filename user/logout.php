<?php
session_name('user_session');
session_start();

// Clear session
session_unset();
session_destroy();

// Clear cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

header("Location: login.php");
exit();
