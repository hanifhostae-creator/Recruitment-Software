<?php
// Copy this file to inc/config.php on the server and fill in your values.
// inc/config.php is ignored by Git, so your passwords never go to GitHub.

// ---- Database (MySQL / MariaDB from hPanel → Databases → MySQL Databases)
define('DB_DRIVER', 'mysql');          // 'mysql' for the live site; 'sqlite' keeps everything in data/rh.sqlite
define('DB_HOST', 'localhost');        // on Hostinger this is normally localhost
define('DB_PORT', 3306);
define('DB_NAME', 'u123456789_rhcrm'); // the full database name shown in hPanel
define('DB_USER', 'u123456789_rhuser');// the full database user name shown in hPanel
define('DB_PASS', 'change-me');        // the database user password

// ---- Secret key used to encrypt stored secrets (SMTP password, WhatsApp token).
// Put a long random value here ONCE and never change it afterwards. Example: 64 random letters and numbers.
define('APP_KEY', 'replace-with-a-long-random-secret');

// ---- Start empty (live) or with sample data (testing)
define('DEMO_DATA', false);            // false = empty system; the first visit shows the "Create the Super Admin" screen

// ---- Sign staff out after this many minutes without activity
define('SESSION_IDLE_MINUTES', 120);
