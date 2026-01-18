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

// ID i produktit nga URL
$update_id = (int)($_GET['update'] ?? 0);
if ($update_id <= 0) {
   header('Location: admin_products.php');
   exit;
}

// Merr produktin
$select_products = $conn->prepare("SELECT * FROM `products` WHERE id = ? LIMIT 1");
$select_products->execute([$update_id]);
$product = $select_products->fetch(PDO::FETCH_ASSOC);

if (!$product) {
   header('Location: admin_products.php');
   exit;
}

// --------------------
// UPDATE PRODUCT
// --------------------
if (isset($_POST['update_product'])) {

   $pid       = (int)($_POST['pid'] ?? 0);
   $name      = clean_text($_POST['name'] ?? '');
   $price     = (int)($_POST['price'] ?? 0);
   $category  = clean_text($_POST['category'] ?? '');
   $details   = clean_text($_POST['details'] ?? '');
   $old_image = (string)($_POST['old_image'] ?? '');

   if ($pid !== $update_id) {
      $message[] = 'Invalid product update!';
   }

   if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
      $message[] = 'Product name must be 2-100 characters!';
   }

   if ($price < 0) {
      $message[] = 'Price must be 0 or more!';
   }

   if (!in_array($category, $allowed_categories, true)) {
      $message[] = 'Invalid category!';
   }

   if ($details === '' || mb_strlen($details) < 5 || mb_strlen($details) > 500) {
      $message[] = 'Details must be 5-500 characters!';
   }

   // Emër unik (për produkt tjetër)
   if (empty($message)) {
      $check_name = $conn->prepare("SELECT id FROM `products` WHERE name = ? AND id != ? LIMIT 1");
      $check_name->execute([$name, $pid]);
      if ($check_name->rowCount() > 0) {
         $message[] = 'product name already exist!';
      }
   }

   // Update fields (pa image)
   if (empty($message)) {
      $update_product = $conn->prepare("UPDATE `products` SET name = ?, category = ?, details = ?, price = ? WHERE id = ?");
      $update_product->execute([$name, $category, $details, $price, $pid]);
      $message[] = 'product updated successfully!';

      // refresh lokal për shfaqje
      $product['name'] = $name;
      $product['category'] = $category;
      $product['details'] = $details;
      $product['price'] = $price;
   }

   // Update image (optional)
   if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {

      $image_name = $_FILES['image']['name'];
      $image_size = (int)($_FILES['image']['size'] ?? 0);
      $image_tmp  = $_FILES['image']['tmp_name'] ?? '';

      $ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
      $allowed_ext = ['jpg','jpeg','png'];

      if (!in_array($ext, $allowed_ext, true)) {
         $message[] = 'Image must be JPG, JPEG, or PNG!';
      } elseif ($image_size > 2000000) {
         $message[] = 'image size is too large! Max 2MB.';
      } elseif (empty($image_tmp)) {
         $message[] = 'Image upload failed!';
      } else {
         $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($image_name, PATHINFO_FILENAME));
         $new_image = $base . '_' . time() . '.' . $ext;
         $image_folder = 'uploaded_img/' . $new_image;

         $update_image = $conn->prepare("UPDATE `products` SET image = ? WHERE id = ?");
         $ok = $update_image->execute([$new_image, $pid]);

         if ($ok) {
            move_uploaded_file($image_tmp, $image_folder);

            // fshi të vjetrën (mos fshi default)
            if (!empty($old_image) && $old_image !== 'default.png') {
               $old_path = 'uploaded_img/' . $old_image;
               if (file_exists($old_path)) {
                  @unlink($old_path);
               }
            }

            $product['image'] = $new_image;
            $message[] = 'image updated successfully!';
         } else {
            $message[] = 'failed to update image!';
         }
      }
   }

   header('Location: admin_update_product.php?update=' . $update_id);
   exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>update products</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>

<section class="update-product">

   <h1 class="title">update product</h1>

   <form action="" method="post" enctype="multipart/form-data">
      <input type="hidden" name="old_image" value="<?= htmlspecialchars((string)$product['image']); ?>">
      <input type="hidden" name="pid" value="<?= (int)$product['id']; ?>">

      <img src="uploaded_img/<?= htmlspecialchars((string)$product['image']); ?>" alt="">

      <input type="text" name="name" placeholder="enter product name" required class="box"
             value="<?= htmlspecialchars((string)$product['name']); ?>">

      <input type="number" name="price" min="0" placeholder="enter product price" required class="box"
             value="<?= (int)$product['price']; ?>">

      <select name="category" class="box" required>
         <option value="vegitables" <?= (($product['category'] ?? '') === 'vegitables') ? 'selected' : ''; ?>>Vegetables</option>
         <option value="fruits" <?= (($product['category'] ?? '') === 'fruits') ? 'selected' : ''; ?>>Fruits</option>
         <option value="meat" <?= (($product['category'] ?? '') === 'meat') ? 'selected' : ''; ?>>Meat</option>
         <option value="fish" <?= (($product['category'] ?? '') === 'fish') ? 'selected' : ''; ?>>Fish</option>
      </select>

      <textarea name="details" required placeholder="enter product details" class="box" cols="30" rows="10"><?= htmlspecialchars((string)$product['details']); ?></textarea>

      <input type="file" name="image" class="box" accept="image/jpg, image/jpeg, image/png">

      <div class="flex-btn">
         <input type="submit" class="btn" value="update product" name="update_product">
         <a href="admin_products.php" class="option-btn">Go back</a>
      </div>
   </form>

</section>

<script src="js/script.js"></script>

</body>
</html>
