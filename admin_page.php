<?php

// Admin guard + remember me + idle timeout + logs
require_once __DIR__ . '/app/includes/admin_header.php';

/**
 * Në këtë pikë je i sigurt që:
 * - je i loguar si admin
 * - session është aktive
 * - remember-me është trajtuar
 * - idle timeout është trajtuar
 */

// Total pendings (SUM)
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_price), 0) FROM `orders` WHERE payment_status = ?");
$stmt->execute(['pending']);
$total_pendings = (int)$stmt->fetchColumn();

// Total completed (SUM)
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_price), 0) FROM `orders` WHERE payment_status = ?");
$stmt->execute(['completed']);
$total_completed = (int)$stmt->fetchColumn();

// Orders count
$stmt = $conn->prepare("SELECT COUNT(*) FROM `orders`");
$stmt->execute();
$number_of_orders = (int)$stmt->fetchColumn();

// Products count
$stmt = $conn->prepare("SELECT COUNT(*) FROM `products`");
$stmt->execute();
$number_of_products = (int)$stmt->fetchColumn();

// Users count
$stmt = $conn->prepare("SELECT COUNT(*) FROM `users` WHERE user_type = ?");
$stmt->execute(['user']);
$number_of_users = (int)$stmt->fetchColumn();

// Admins count
$stmt = $conn->prepare("SELECT COUNT(*) FROM `users` WHERE user_type = ?");
$stmt->execute(['admin']);
$number_of_admins = (int)$stmt->fetchColumn();

// Total accounts count
$stmt = $conn->prepare("SELECT COUNT(*) FROM `users`");
$stmt->execute();
$number_of_accounts = (int)$stmt->fetchColumn();

// Messages count
$stmt = $conn->prepare("SELECT COUNT(*) FROM `message`");
$stmt->execute();
$number_of_messages = (int)$stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Admin Page</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>

<!-- admin_header.php është përfshirë lart (para output), prandaj këtu s’duhet më -->

<section class="dashboard">

   <h1 class="title">Dashboard</h1>

   <div class="box-container">

      <div class="box">
         <h3>$<?= $total_pendings; ?>/-</h3>
         <p>Total pendings</p>
         <a href="admin_orders.php" class="btn">See orders</a>
      </div>

      <div class="box">
         <h3>$<?= $total_completed; ?>/-</h3>
         <p>Completed orders</p>
         <a href="admin_orders.php" class="btn">See orders</a>
      </div>

      <div class="box">
         <h3><?= $number_of_orders; ?></h3>
         <p>Orders placed</p>
         <a href="admin_orders.php" class="btn">See orders</a>
      </div>

      <div class="box">
         <h3><?= $number_of_products; ?></h3>
         <p>Products added</p>
         <a href="admin_products.php" class="btn">See products</a>
      </div>

      <div class="box">
         <h3><?= $number_of_users; ?></h3>
         <p>Total users</p>
         <a href="admin_users.php" class="btn">See accounts</a>
      </div>

      <div class="box">
         <h3><?= $number_of_admins; ?></h3>
         <p>Total admins</p>
         <a href="admin_users.php" class="btn">See accounts</a>
      </div>

      <div class="box">
         <h3><?= $number_of_accounts; ?></h3>
         <p>Total accounts</p>
         <a href="admin_users.php" class="btn">See accounts</a>
      </div>

      <div class="box">
         <h3><?= $number_of_messages; ?></h3>
         <p>Total messages</p>
         <a href="admin_contacts.php" class="btn">See messages</a>
      </div>

   </div>

</section>

<script src="js/script.js"></script>

</body>
</html>
