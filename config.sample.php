<?php
// Copy this file to config.php and fill in your own values.
// config.php is gitignored because it holds secrets — never commit it.

define('DB_HOST', 'localhost');
define('DB_NAME', 'sims');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', '/sims');
// Site URL used for verification links (include http:// or https:// and host)
define('SITE_URL', 'http://localhost');

// Gmail SMTP settings for sending notification emails.
// Generate a Gmail App Password: https://myaccount.google.com/apppasswords
define('GMAIL_SMTP_HOST', 'smtp.gmail.com');
define('GMAIL_SMTP_PORT', 587);
define('GMAIL_SMTP_USER', 'you@example.com');
define('GMAIL_SMTP_PASS', 'your-16-char-app-password');
define('MAIL_FROM', 'you@example.com');

// Admin email — receives notifications when students register
define('ADMIN_EMAIL', 'you@example.com');
?>
