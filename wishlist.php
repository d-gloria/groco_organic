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

// --------------------
// ADD TO CART
// --------------------
if(isset($_POST['add_to_cart'])){

   $pid     = (int)($_POST['pid'] ?? 0);
   $p_name  = trim((string)($_POST['p_name'] ?? ''));
   $p_price = (int)($_POST['p_price'] ?? 0);
   $p_image = trim((string)($_POST['p_image'] ?? ''));
   $p_qty   = (int)($_POST['p_qty'] ?? 1);

   if($pid <= 0){
      $message[] = 'Invalid product!';
   }elseif($p_qty < 1){
      $message[] = 'Quantity must be at least 1!';
   }elseif($p_price < 0){
      $message[] = 'Invalid price!';
   }else{

      // Check if already in cart (use pid + user_id)
      $check_cart = $conn->prepare("SELECT id FROM `cart` WHERE pid = ? AND user_id = ? LIMIT 1");
      $check_cart->execute([$pid, $user_id]);

      if($check_cart->rowCount() > 0){
         $message[] = 'already added to cart!';
      }else{

         // If exists in wishlist, remove (use pid + user_id)
         $check_wishlist = $conn->prepare("SELECT id FROM `wishlist` WHERE pid = ? AND user_id = ? LIMIT 1");
         $check_wishlist->execute([$pid, $user_id]);

         if($check_wishlist->rowCount() > 0){
            $delete_wishlist = $conn->prepare("DELETE FROM `wishlist` WHERE pid = ? AND user_id = ?");
            $delete_wishlist->execute([$pid, $user_id]);
         }

         $insert_cart = $conn->prepare("INSERT INTO `cart`(user_id, pid, name, price, quantity, image) VALUES(?,?,?,?,?,?)");
         $insert_cart->execute([$user_id, $pid, $p_name, $p_price, $p_qty, $p_image]);

         $message[] = 'added to cart!';
      }
   }
}

// --------------------
// DELETE ONE
// --------------------
if(isset($_GET['delete'])){

   $delete_id = (int)($_GET['delete'] ?? 0);
   if($delete_id > 0){
      $delete_wishlist_item = $conn->prepare("DELETE FROM `wishlist` WHERE id = ? AND user_id = ?");
      $delete_wishlist_item->execute([$delete_id, $user_id]);
   }
   header('Location: wishlist.php');
   exit;
}

// --------------------
// DELETE ALL
// --------------------
if(isset($_GET['delete_all'])){

   $delete_wishlist_item = $conn->prepare("DELETE FROM `wishlist` WHERE user_id = ?");
   $delete_wishlist_item->execute([$user_id]);

   header('Location: wishlist.php');
   exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Wishlist</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
</head>
<body>

<section class="wishlist">

   <h1 class="title">Products added</h1>

   <div class="box-container">

   <?php
      $grand_total = 0;

      $select_wishlist = $conn->prepare("SELECT * FROM `wishlist` WHERE user_id = ? ORDER BY id DESC");
      $select_wishlist->execute([$user_id]);

      if($select_wishlist->rowCount() > 0){
         while($fetch_wishlist = $select_wishlist->fetch(PDO::FETCH_ASSOC)){
            $price = (int)$fetch_wishlist['price'];
            $grand_total += $price;
   ?>
   <form action="" method="POST" class="box">
      <a href="wishlist.php?delete=<?= (int)$fetch_wishlist['id']; ?>" class="fas fa-times" onclick="return confirm('delete this from wishlist?');"></a>
      <a href="view_page.php?pid=<?= (int)$fetch_wishlist['pid']; ?>" class="fas fa-eye"></a>

      <img src="uploaded_img/<?= htmlspecialchars((string)$fetch_wishlist['image']); ?>" alt="">
      <div class="name"><?= htmlspecialchars((string)$fetch_wishlist['name']); ?></div>
      <div class="price">$<?= $price; ?>/-</div>

      <input type="number" min="1" value="1" class="qty" name="p_qty" required>

      <input type="hidden" name="pid" value="<?= (int)$fetch_wishlist['pid']; ?>">
      <input type="hidden" name="p_name" value="<?= htmlspecialchars((string)$fetch_wishlist['name']); ?>">
      <input type="hidden" name="p_price" value="<?= $price; ?>">
      <input type="hidden" name="p_image" value="<?= htmlspecialchars((string)$fetch_wishlist['image']); ?>">

      <input type="submit" value="add to cart" name="add_to_cart" class="btn">
   </form>
   <?php
         }
      }else{
         echo '<p class="empty">your wishlist is empty</p>';
      }
   ?>
   </div>

   <div class="wishlist-total">
      <p>Total: <span>$<?= (int)$grand_total; ?>/-</span></p>
      <a href="shop.php" class="option-btn">Continue shopping</a>

      <a href="wishlist.php?delete_all" class="delete-btn <?= ($grand_total > 0) ? '' : 'disabled'; ?>"
         onclick="return confirm('delete all items from wishlist?');">
         Delete All
      </a>
   </div>

</section>

<?php require_once __DIR__ . '/app/includes/footer.php'; ?>

<script src="js/script.js"></script>

</body>
</html>
