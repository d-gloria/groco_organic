<?php

require_once __DIR__ . '/app/includes/header.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
   header('Location: login.php');
   exit;
}
$user_id = (int)$user_id;

function nice_method(string $m): string {
   $m = strtolower(trim($m));
   if ($m === 'cash') return 'Cash on delivery';
   if ($m === 'card') return 'Credit card';
   if ($m === 'paypal') return 'PayPal';
   return $m;
}

function nice_status(string $s): string {
   $s = strtolower(trim($s));
   if ($s === 'pending') return 'pending';
   if ($s === 'paid') return 'paid';
   return $s;
}

function status_color(string $s): string {
   $s = strtolower(trim($s));
   if ($s === 'pending') return 'red';
   if ($s === 'paid') return 'green';
   return 'orange';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Orders</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
</head>
<body>

<section class="placed-orders">

   <h1 class="title">Placed orders</h1>

   <div class="box-container">

   <?php
      $select_orders = $conn->prepare("SELECT * FROM `orders` WHERE user_id = ? ORDER BY id DESC");
      $select_orders->execute([$user_id]);

      if($select_orders->rowCount() > 0){
         while($fetch_orders = $select_orders->fetch(PDO::FETCH_ASSOC)){

            $status_raw = (string)($fetch_orders['payment_status'] ?? 'pending');
            $status = nice_status($status_raw);
            $color  = status_color($status_raw);

            $method_raw = (string)($fetch_orders['method'] ?? '');
            $method = nice_method($method_raw);

            $paid_at = $fetch_orders['paid_at'] ?? null;
   ?>
   <div class="box">
      <p> Placed on: <span><?= htmlspecialchars((string)$fetch_orders['placed_on']); ?></span> </p>
      <p> Name: <span><?= htmlspecialchars((string)$fetch_orders['name']); ?></span> </p>
      <p> Number: <span><?= htmlspecialchars((string)$fetch_orders['number']); ?></span> </p>
      <p> Email: <span><?= htmlspecialchars((string)$fetch_orders['email']); ?></span> </p>
      <p> Address: <span><?= htmlspecialchars((string)$fetch_orders['address']); ?></span> </p>
      <p> Payment method: <span><?= htmlspecialchars($method); ?></span> </p>
      <p> Your order: <span><?= htmlspecialchars((string)$fetch_orders['total_products']); ?></span> </p>
      <p> Total price: <span>$<?= (int)$fetch_orders['total_price']; ?>/-</span> </p>

      <p> Payment status:
         <span style="color:<?= htmlspecialchars($color); ?>">
            <?= htmlspecialchars($status); ?>
         </span>
      </p>

      <?php if (!empty($paid_at)): ?>
         <p> Paid at: <span><?= htmlspecialchars((string)$paid_at); ?></span> </p>
      <?php endif; ?>
   </div>
   <?php
         }
      } else {
         echo '<p class="empty">no orders placed yet!</p>';
      }
   ?>

   </div>

</section>

<?php require_once __DIR__ . '/app/includes/footer.php'; ?>

<script src="js/script.js"></script>

</body>
</html>
