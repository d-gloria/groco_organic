<?php

// User guard + remember me + idle timeout + logs (+ navbar)
require_once __DIR__ . '/app/includes/header.php';

// Merr user_id pas header guard
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
   header('Location: login.php');
   exit;
}
$user_id = (int)$user_id;

// DELETE single item (only if it belongs to this user)
if (isset($_GET['delete'])) {
   $delete_id = (int)($_GET['delete'] ?? 0);

   if ($delete_id > 0) {
      $delete_cart_item = $conn->prepare("DELETE FROM `cart` WHERE id = ? AND user_id = ?");
      $delete_cart_item->execute([$delete_id, $user_id]);
   }

   header('Location: cart.php');
   exit;
}

// DELETE all items for this user
if (isset($_GET['delete_all'])) {
   $delete_cart_item = $conn->prepare("DELETE FROM `cart` WHERE user_id = ?");
   $delete_cart_item->execute([$user_id]);

   header('Location: cart.php');
   exit;
}

// UPDATE qty (only for this user)
if (isset($_POST['update_qty'])) {
   $cart_id = (int)($_POST['cart_id'] ?? 0);
   $p_qty   = (int)($_POST['p_qty'] ?? 0);

   if ($cart_id <= 0) {
      $message[] = 'Invalid cart item!';
   } elseif ($p_qty < 1) {
      $message[] = 'Quantity must be at least 1!';
   } else {
      $update_qty = $conn->prepare("UPDATE `cart` SET quantity = ? WHERE id = ? AND user_id = ?");
      $update_qty->execute([$p_qty, $cart_id, $user_id]);
      $message[] = 'Cart quantity updated';
   }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Shopping cart</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
</head>
<body>

<section class="shopping-cart">

   <h1 class="title">Products added</h1>

   <div class="box-container">

   <?php
      $grand_total = 0;
      $select_cart = $conn->prepare("SELECT * FROM `cart` WHERE user_id = ?");
      $select_cart->execute([$user_id]);

      if ($select_cart->rowCount() > 0) {
         while ($fetch_cart = $select_cart->fetch(PDO::FETCH_ASSOC)) {
            $price = (int)$fetch_cart['price'];
            $qty = (int)$fetch_cart['quantity'];
            $sub_total = $price * $qty;
            $grand_total += $sub_total;
   ?>
   <form action="" method="POST" class="box">
      <a href="cart.php?delete=<?= (int)$fetch_cart['id']; ?>" class="fas fa-times" onclick="return confirm('delete this from cart?');"></a>
      <a href="view_page.php?pid=<?= (int)$fetch_cart['pid']; ?>" class="fas fa-eye"></a>
      <img src="uploaded_img/<?= htmlspecialchars((string)$fetch_cart['image']); ?>" alt="">
      <div class="name"><?= htmlspecialchars((string)$fetch_cart['name']); ?></div>
      <div class="price">$<?= $price; ?>/-</div>

      <input type="hidden" name="cart_id" value="<?= (int)$fetch_cart['id']; ?>">

      <div class="flex-btn">
         <input type="number" min="1" value="<?= $qty; ?>" class="qty" name="p_qty" required>
         <input type="submit" value="update" name="update_qty" class="option-btn">
      </div>

      <div class="sub-total">
         Sub total: <span>$<?= $sub_total; ?>/-</span>
      </div>
   </form>
   <?php
         }
      } else {
         echo '<p class="empty">your cart is empty</p>';
      }
   ?>
   </div>

   <div class="cart-total">
      <p>Grand total : <span>$<?= (int)$grand_total; ?>/-</span></p>
      <a href="shop.php" class="option-btn">Continue shopping</a>

      <a href="cart.php?delete_all" class="delete-btn <?= ($grand_total > 0) ? '' : 'disabled'; ?>"
         onclick="return confirm('delete all items from cart?');">
         Delete all
      </a>

      <a href="checkout.php" class="btn <?= ($grand_total > 0) ? '' : 'disabled'; ?>">
         Proceed to checkout
      </a>
   </div>

</section>

<?php require_once __DIR__ . '/app/includes/footer.php'; ?>

<script src="js/script.js"></script>

</body>
</html>
