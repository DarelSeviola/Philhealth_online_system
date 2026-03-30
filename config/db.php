<?php
// config/db.php


/* =========================================================
   DATABASE SETTINGS
   These are the database login details.
   XAMPP default settings are used here.
========================================================= */

$db_host = "127.0.0.1";      // Database server (localhost)
$db_user = "root";           // MySQL username
$db_pass = "";               // MySQL password (empty in XAMPP)
$db_name = "philhealth_queue"; // Name of the database



/* =========================================================
   CREATE DATABASE CONNECTION
   This connects PHP to the MySQL database.
========================================================= */

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);



/* =========================================================
   CHECK IF CONNECTION FAILED
   If database connection fails, stop the system
   and show an error message.
========================================================= */

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}



/* =========================================================
   SET DATABASE CHARACTER SET
   utf8mb4 allows storing all characters properly
   including special characters and emojis.
========================================================= */

$conn->set_charset("utf8mb4");
