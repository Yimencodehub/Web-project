<?php
// ================================================================
// REAL EMAIL SMTP CONFIGURATION ( config/mail.php )
// ================================================================

// Set to FALSE for REAL email sending to actual user inbox
define('MAIL_DEV_MODE', false);

// ── SMTP SETTINGS FOR REAL EMAIL DELIVERY ──
// For Gmail:
// 1. Turn ON 2-Step Verification in your Google Account (myaccount.google.com/security)
// 2. Generate an "App Password" (myaccount.google.com/apppasswords)
// 3. Paste your email in MAIL_USERNAME and the 16-letter App Password in MAIL_PASSWORD
define('MAIL_HOST',       'smtp.gmail.com');      // e.g. smtp.gmail.com / smtp.office365.com
define('MAIL_PORT',       587);                   // 587 (TLS) or 465 (SSL)
define('MAIL_USERNAME',   'yimenanmaw711@gmail.com'); // Put your real Gmail address here
define('MAIL_PASSWORD',   'doap ggzb orot xfud'); // Put your 16-letter App Password here
define('MAIL_FROM_EMAIL', 'yimenanmaw@gmail.com'); // Sender email
define('MAIL_FROM_NAME',  'City Public Library');
define('MAIL_ENCRYPTION', 'tls');                // 'tls' or 'ssl'

// Token expiration in minutes
define('RESET_TOKEN_EXPIRY_MINUTES', 60);
