<?php
// config/env.php

/* =========================================================
   LOAD ENV FILE
   This function reads the .env file and loads the values
   into the system so PHP can use them.
========================================================= */
function load_env(string $path): void
{

    /* =====================================================
       CHECK IF .env FILE EXISTS
       If the file does not exist, stop the function.
    ===================================================== */
    if (!file_exists($path)) {
        return;
    }


    /* =====================================================
       READ ALL LINES FROM .env FILE
       Each line contains a variable like:
       DB_HOST=localhost
    ===================================================== */
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!$lines) return;


    /* =====================================================
       PROCESS EACH LINE FROM THE FILE
    ===================================================== */
    foreach ($lines as $line) {

        $line = trim($line);

        /* Skip comments (#) and empty lines */
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }


        /* =================================================
           SPLIT KEY AND VALUE
           Example:
           DB_HOST=localhost
           KEY = DB_HOST
           VALUE = localhost
        ================================================= */
        $pos = strpos($line, '=');

        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));


        /* =================================================
           REMOVE QUOTES IF VALUE HAS QUOTES
           Example:
           DB_PASS="1234"
           becomes
           1234
        ================================================= */
        if (
            (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))
        ) {
            $val = substr($val, 1, -1);
        }


        /* =================================================
           SAVE VALUE TO SYSTEM ENVIRONMENT
           This allows PHP to use the variable later
           Example:
           getenv("DB_HOST")
        ================================================= */
        putenv("{$key}={$val}");

        /* Also store in PHP ENV array */
        $_ENV[$key] = $val;
    }
}
