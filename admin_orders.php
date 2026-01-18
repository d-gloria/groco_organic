<?php

// Admin guard + remember me + idle timeout + logs
require_once __DIR__ . '/app/includes/admin_header.php';

$message = [];

// --------------------
// UPDATE ORDER (payment + fulfillment)
// --------------------
if(isset($_POST['update_order'])){

   $order_id = (int)($_POST['order_id'] ?? 0);
   $update_payment = strtolower(trim((string)($_POST['update_payment'] ?? '')));
   $update_status  = strtolower(trim((string)($_POST['update_status'] ?? '')));

   $allowed_payment = ['pending', 'paid'];
   $allowed_status  = ['unfulfilled', 'fulfilled'];

   if($order_id <= 0){
      $message[] = 'Invalid order id!';
   } elseif(!in_array($update_payment, $allowed_payment, true)){
      $message[] = 'Invalid payment status!';
   } elseif(!in_array($update_status, $allowed_status, true)){
      $message[] = 'Invalid order status!';
   } else {

      // nëse admin e bën paid, vendos paid_at nëse është bosh
      $update_orders = $conn->prepare("
         UPDATE orders
         SET payment_status = ?,
             status = ?,
             paid_at = CASE
               WHEN ? = 'paid' AND (paid_at IS NULL OR paid_at = '0000-00-00 00:00:00') THEN NOW()
               ELSE paid_at
             END
         WHERE id = ?
      ");
      $update_orders->execute([$update_payment, $update_status, $update_payment, $order_id]);

      $message[] = 'Order has been updated!';
   }

   header('Location: admin_orders.php');
   exit;
}

// --------------------
// DELETE ORDER
// --------------------
if(isset($_GET['delete'])){

   $delete_id = (int)($_GET['delete'] ?? 0);

   if($delete_id > 0){
      $delete_orders = $conn->prepare("DELETE FROM orders WHERE id = ?");
      $delete_orders->execute([$delete_id]);
      $message[] = 'order deleted!';
   }

   header('Location: admin_orders.php');
   exit;
}

function nice_method(string $m): string {
   $m = strtolower(trim($m));
   if ($m === 'cash') return 'Cash on delivery';
   if ($m === 'card') return 'Credit card';
   if ($m === 'paypal') return 'PayPal';
   return $m;
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
   <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>

<section class="placed-orders">

   <h1 class="title">Placed orders</h1>

   <div class="box-container">

      <?php
         $select_orders = $conn->prepare("SELECT * FROM orders ORDER BY id DESC");
         $select_orders->execute();

         if($select_orders->rowCount() > 0){
            while($fetch_orders = $select_orders->fetch(PDO::FETCH_ASSOC)){

               $payment_status = strtolower((string)($fetch_orders['payment_status'] ?? 'pending'));
               if (!in_array($payment_status, ['pending','paid'], true)) $payment_status = 'pending';

               $order_status = strtolower((string)($fetch_orders['status'] ?? 'unfulfilled'));
               if (!in_array($order_status, ['unfulfilled','fulfilled'], true)) $order_status = 'unfulfilled';

               $method = nice_method((string)($fetch_orders['method'] ?? ''));

      ?>
      <div class="box">
         <p> Order id: <span><?= (int)$fetch_orders['id']; ?></span> </p>
         <p> User id: <span><?= (int)$fetch_orders['user_id']; ?></span> </p>
         <p> Placed on: <span><?= htmlspecialchars((string)$fetch_orders['placed_on']); ?></span> </p>
         <p> Name: <span><?= htmlspecialchars((string)$fetch_orders['name']); ?></span> </p>
         <p> Email: <span><?= htmlspecialchars((string)$fetch_orders['email']); ?></span> </p>
         <p> Number: <span><?= htmlspecialchars((string)$fetch_orders['number']); ?></span> </p>
         <p> Address: <span><?= htmlspecialchars((string)$fetch_orders['address']); ?></span> </p>
         <p> Total products: <span><?= htmlspecialchars((string)$fetch_orders['total_products']); ?></span> </p>
         <p> Total price: <span>$<?= (int)$fetch_orders['total_price']; ?>/-</span> </p>
         <p> Payment method: <span><?= htmlspecialchars($method); ?></span> </p>

         <?php if (!empty($fetch_orders['paid_at'])): ?>
            <p> Paid at: <span><?= htmlspecialchars((string)$fetch_orders['paid_at']); ?></span> </p>
         <?php endif; ?>

         <?php if (!empty($fetch_orders['paypal_order_id'])): ?>
            <p> PayPal order id: <span><?= htmlspecialchars((string)$fetch_orders['paypal_order_id']); ?></span> </p>
         <?php endif; ?>

         <?php if (!empty($fetch_orders['paypal_capture_id'])): ?>
            <p> PayPal capture id: <span><?= htmlspecialchars((string)$fetch_orders['paypal_capture_id']); ?></span> </p>
         <?php endif; ?>

         <form action="" method="POST">
            <input type="hidden" name="order_id" value="<?= (int)$fetch_orders['id']; ?>">

            <p style="margin-top:10px;margin-bottom:6px;">Payment status</p>
            <select name="update_payment" class="drop-down" required>
               <option value="pending" <?= ($payment_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
               <option value="paid" <?= ($payment_status === 'paid') ? 'selected' : ''; ?>>Paid</option>
            </select>

            <p style="margin-top:10px;margin-bottom:6px;">Order status</p>
            <select name="update_status" class="drop-down" required>
               <option value="unfulfilled" <?= ($order_status === 'unfulfilled') ? 'selected' : ''; ?>>Unfulfilled</option>
               <option value="fulfilled" <?= ($order_status === 'fulfilled') ? 'selected' : ''; ?>>Fulfilled</option>
            </select>

            <div class="flex-btn" style="margin-top:10px;">
               <input type="submit" name="update_order" class="option-btn" value="update">
               <a href="admin_orders.php?delete=<?= (int)$fetch_orders['id']; ?>" class="delete-btn"
                  onclick="return confirm('delete this order?');">
                  Delete
               </a>
            </div>
         </form>
      </div>
      <?php
            }
         } else {
            echo '<p class="empty">no orders placed yet!</p>';
         }
      ?>

   </div>

</section>

<script src="js/script.js"></script>

</body>
</html>
