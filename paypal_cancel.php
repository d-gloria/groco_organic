<?php
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['pending_paypal_order_id']);
header('Location: checkout.php');
exit;
