<?php
// PayPal Sandbox config
// Merr Client ID / Secret nga PayPal Developer Dashboard (Sandbox)

define('PAYPAL_MODE', 'sandbox'); // 'sandbox' or 'live'

define('PAYPAL_CLIENT_ID', 'YOUR_ID');
define('PAYPAL_SECRET',    'YOUR_SECRET');

define('PAYPAL_API_BASE', PAYPAL_MODE === 'live'
   ? 'https://api-m.paypal.com'
   : 'https://api-m.sandbox.paypal.com'
);
