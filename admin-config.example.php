<?php
// Copy this file to admin-config.php on the server and fill in your values.
// NEVER commit admin-config.php to GitHub — it is in .gitignore.

// Admin panel password (you choose this)
define('ADMIN_PASSWORD', 'changeme');

// reCAPTCHA v2 Secret Key — from https://www.google.com/recaptcha/admin
// (the Site Key goes in your HTML files as data-sitekey="...")
define('RECAPTCHA_SECRET', 'your_recaptcha_secret_key_here');

// Email address that receives new order notifications
define('ADMIN_EMAIL', 'your@email.com');
