<?php

// Admin guard + remember me + idle timeout + logs
require_once __DIR__ . '/app/includes/admin_header.php';

// --------------------
// Helpers
// --------------------
function clean_text($v) {
   $v = trim((string)$v);
   $v = preg_replace('/\s+/', ' ', $v);
   return $v;
}

$allowed_categories = ['vegetables','fruits','meat','fish'];

// --------------------
// ADD PRODUCT
// --------------------
if(isset($_POST['add_product'])){

   $name     = clean_text($_POST['name'] ?? '');
   $price    = (int)($_POST['price'] ?? 0);
   $category = clean_text($_POST['category'] ?? '');
   $details  = clean_text($_POST['details'] ?? '');

   // Validim back-end
   if($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100){
      $message[] = 'Product name must be 2-100 characters!';
   }

   if($price < 0){
      $message[] = 'Price must be 0 or more!';
   }

   if(!in_array($category, $allowed_categories, true)){
      $message[] = 'Invalid category!';
   }

   if($details === '' || mb_strlen($details) < 5 || mb_strlen($details) > 500){
      $message[] = 'Details must be 5-500 characters!';
   }

   // Kontroll për emër unik
   if(empty($message)){
      $select_products = $conn->prepare("SELECT id FROM `products` WHERE name = ? LIMIT 1");
      $select_products->execute([$name]);
      if($select_products->rowCount() > 0){
         $message[] = 'product name already exist!';
      }
   }

   // Image validations
   $new_image = null;
   if(empty($message)){
      if(!isset($_FILES['image']) || empty($_FILES['image']['name'])){
         $message[] = 'Please select an image!';
      } else {
         $image_name = $_FILES['image']['name'];
         $image_size = (int)($_FILES['image']['size'] ?? 0);
         $image_tmp  = $_FILES['image']['tmp_name'] ?? '';

         $ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
         $allowed_ext = ['jpg','jpeg','png'];

         if(!in_array($ext, $allowed_ext, true)){
            $message[] = 'Image must be JPG, JPEG, or PNG!';
         } elseif($image_size > 2000000){
            $message[] = 'image size is too large! Max 2MB.';
         } elseif(empty($image_tmp)){
            $message[] = 'Image upload failed!';
         } else {
            $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($image_name, PATHINFO_FILENAME));
            $new_image = $base . '_' . time() . '.' . $ext;
         }
      }
   }

   // Insert + move file
   if(empty($message) && $new_image !== null){
      $insert_products = $conn->prepare("INSERT INTO `products`(name, category, details, price, image) VALUES(?,?,?,?,?)");
      $ok = $insert_products->execute([$name, $category, $details, $price, $new_image]);

      if($ok){
         $image_folder = 'uploaded_img/' . $new_image;
         move_uploaded_file($_FILES['image']['tmp_name'], $image_folder);
         $message[] = 'new product added!';
      } else {
         $message[] = 'failed to add product!';
      }
   }
}

// --------------------
// DELETE PRODUCT
// --------------------
if(isset($_GET['delete'])){

   $delete_id = (int)($_GET['delete'] ?? 0);

   if($delete_id > 0){

      $select_delete_image = $conn->prepare("SELECT image FROM `products` WHERE id = ? LIMIT 1");
      $select_delete_image->execute([$delete_id]);
      $fetch_delete_image = $select_delete_image->fetch(PDO::FETCH_ASSOC);

      $delete_products = $conn->prepare("DELETE FROM `products` WHERE id = ?");
      $delete_products->execute([$delete_id]);

      $conn->prepare("DELETE FROM `wishlist` WHERE pid = ?")->execute([$delete_id]);
      $conn->prepare("DELETE FROM `cart` WHERE pid = ?")->execute([$delete_id]);

      if($fetch_delete_image && !empty($fetch_delete_image['image'])){
         $path = 'uploaded_img/' . $fetch_delete_image['image'];
         if(file_exists($path)){
            @unlink($path);
         }
      }

      $message[] = 'product deleted!';
   }

   header('Location: admin_products.php');
   exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>products</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>

<section class="add-products">

   <h1 class="title">Add new product</h1>

   <form action="" method="POST" enctype="multipart/form-data">
      <div class="flex">
         <div class="inputBox">
            <input type="text" name="name" class="box" required placeholder="enter product name">
            <select name="category" class="box" required>
               <option value="" selected disabled>Select category</option>
               <option value="vegetables">Vegetables</option>
               <option value="fruits">Fruits</option>
               <option value="meat">Meat</option>
               <option value="fish">Fish</option>
            </select>
         </div>
         <div class="inputBox">
            <input type="number" min="0" name="price" class="box" required placeholder="enter product price">
            <input type="file" name="image" required class="box" accept="image/jpg, image/jpeg, image/png">
         </div>
      </div>
      <textarea name="details" class="box" required placeholder="enter product details" cols="30" rows="10"></textarea>
      <input type="submit" class="btn" value="add product" name="add_product">
   </form>

</section>

<section class="show-products">

   <h1 class="title">Products added</h1>

   <div class="box-container">

   <?php
      $show_products = $conn->prepare("SELECT * FROM `products` ORDER BY id DESC");
      $show_products->execute();

      if($show_products->rowCount() > 0){
         while($fetch_products = $show_products->fetch(PDO::FETCH_ASSOC)){
   ?>
   <div class="box">
      <div class="price">$<?= (int)$fetch_products['price']; ?>/-</div>
      <img src="uploaded_img/<?= htmlspecialchars((string)$fetch_products['image']); ?>" alt="">
      <div class="name"><?= htmlspecialchars((string)$fetch_products['name']); ?></div>
      <div class="cat"><?= htmlspecialchars((string)$fetch_products['category']); ?></div>
      <div class="details"><?= htmlspecialchars((string)$fetch_products['details']); ?></div>

      <div class="flex-btn">
         <a href="admin_update_product.php?update=<?= (int)$fetch_products['id']; ?>" class="option-btn">update</a>
         <a href="admin_products.php?delete=<?= (int)$fetch_products['id']; ?>" class="delete-btn"
            onclick="return confirm('delete this product?');">
            delete
         </a>
      </div>
   </div>
   <?php
         }
      }else{
         echo '<p class="empty">no products added yet!</p>';
      }
   ?>

   </div>

</section>

<script src="js/script.js"></script>

</body>
</html>
