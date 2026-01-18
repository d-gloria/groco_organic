<?php

// Admin guard + remember me + idle timeout + logs
require_once __DIR__ . '/app/includes/admin_header.php';

// Merr admin_id nga session (pas guard-it)
$admin_id = (int)($_SESSION['admin_id'] ?? 0);

// --------------------
// UPDATE ROLE (admin only)
// --------------------
if (isset($_POST['update_role'])) {

   $user_id  = (int)($_POST['user_id'] ?? 0);
   $new_role = trim((string)($_POST['user_type'] ?? ''));

   $allowed_roles = ['user', 'admin'];

   if ($user_id <= 0) {
      $message[] = 'Invalid user id!';
   } elseif ($user_id === $admin_id) {
      $message[] = 'You cannot change your own role!';
   } elseif (!in_array($new_role, $allowed_roles, true)) {
      $message[] = 'Invalid role!';
   } else {
      $upd = $conn->prepare("UPDATE `users` SET user_type = ? WHERE id = ?");
      $upd->execute([$new_role, $user_id]);
      $message[] = 'User role updated!';
   }

   header('Location: admin_users.php');
   exit;
}

// --------------------
// DELETE USER (admin only)
// --------------------
if (isset($_GET['delete'])) {

   $delete_id = (int)($_GET['delete'] ?? 0);

   if ($delete_id <= 0) {
      $message[] = 'Invalid user id!';
   } elseif ($delete_id === $admin_id) {
      $message[] = 'You cannot delete your own account!';
   } else {

      // Merr foton e userit (për ta fshirë nga disk)
      $select_img = $conn->prepare("SELECT image FROM `users` WHERE id = ? LIMIT 1");
      $select_img->execute([$delete_id]);
      $u = $select_img->fetch(PDO::FETCH_ASSOC);

      // Fshi user
      $delete_users = $conn->prepare("DELETE FROM `users` WHERE id = ?");
      $delete_users->execute([$delete_id]);

      // Fshi data të varura (opsionale por e pastër)
      $conn->prepare("DELETE FROM `cart` WHERE user_id = ?")->execute([$delete_id]);
      $conn->prepare("DELETE FROM `wishlist` WHERE user_id = ?")->execute([$delete_id]);
      $conn->prepare("DELETE FROM `orders` WHERE user_id = ?")->execute([$delete_id]);
      $conn->prepare("DELETE FROM `message` WHERE user_id = ?")->execute([$delete_id]);

      // Fshi foton (mos fshi default)
      if ($u && !empty($u['image']) && $u['image'] !== 'default.png') {
         $path = 'uploaded_img/' . $u['image'];
         if (file_exists($path)) {
            @unlink($path);
         }
      }

      $message[] = 'User deleted!';
   }

   header('Location: admin_users.php');
   exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Users</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>

<section class="user-accounts">

   <h1 class="title">User accounts</h1>

   <div class="box-container">

      <?php
         $select_users = $conn->prepare("SELECT id, name, email, user_type, image FROM `users` ORDER BY id DESC");
         $select_users->execute();

         while($fetch_users = $select_users->fetch(PDO::FETCH_ASSOC)){
            $is_me = ((int)$fetch_users['id'] === $admin_id);
      ?>
      <div class="box" style="<?= $is_me ? 'opacity:0.75;' : ''; ?>">
         <img src="uploaded_img/<?= htmlspecialchars((string)$fetch_users['image']); ?>" alt="">

         <p> user id : <span><?= (int)$fetch_users['id']; ?></span></p>
         <p> username : <span><?= htmlspecialchars((string)$fetch_users['name']); ?></span></p>
         <p> email : <span><?= htmlspecialchars((string)$fetch_users['email']); ?></span></p>

         <p> user type :
            <span style="color:<?= (($fetch_users['user_type'] ?? '') === 'admin') ? 'orange' : 'inherit'; ?>">
               <?= htmlspecialchars((string)$fetch_users['user_type']); ?>
            </span>
         </p>

         <?php if(!$is_me){ ?>
            <form action="" method="POST" style="margin-top:10px;">
               <input type="hidden" name="user_id" value="<?= (int)$fetch_users['id']; ?>">

               <select name="user_type" class="drop-down" required>
                  <option value="user"  <?= (($fetch_users['user_type'] ?? '') === 'user') ? 'selected' : ''; ?>>User</option>
                  <option value="admin" <?= (($fetch_users['user_type'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
               </select>

               <div class="flex-btn" style="margin-top:10px;">
                  <input type="submit" name="update_role" class="option-btn" value="update role">
                  <a href="admin_users.php?delete=<?= (int)$fetch_users['id']; ?>"
                     onclick="return confirm('delete this user?');"
                     class="delete-btn">
                     Delete
                  </a>
               </div>
            </form>
         <?php } else { ?>
            <p><em>This is your admin account.</em></p>
         <?php } ?>

      </div>
      <?php } ?>

   </div>

</section>

<script src="js/script.js"></script>

</body>
</html>
