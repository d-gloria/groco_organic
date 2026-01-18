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

// Merr profilin aktual
$select_profile = $conn->prepare("SELECT id, name, email, password, image, user_type FROM `users` WHERE id = ? LIMIT 1");
$select_profile->execute([$user_id]);
$fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);

if (!$fetch_profile) {
   header('Location: logout.php');
   exit;
}

if (empty($fetch_profile['image'])) {
   $fetch_profile['image'] = 'default.png';
}

// Helper
function clean_text($v) {
   $v = trim((string)$v);
   $v = preg_replace('/\s+/', ' ', $v);
   return $v;
}

if(isset($_POST['update_profile'])){

   // -------- Update name & email --------
   $name  = clean_text($_POST['name'] ?? '');
   $email = strtolower(trim((string)($_POST['email'] ?? '')));

   if($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100){
      $message[] = 'Name must be 2-100 characters!';
   }

   if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100){
      $message[] = 'Email is not valid!';
   }

   // Email unik (për user tjetër)
   if(empty($message)){
      $check_email = $conn->prepare("SELECT id FROM `users` WHERE email = ? AND id != ? LIMIT 1");
      $check_email->execute([$email, $user_id]);
      if($check_email->rowCount() > 0){
         $message[] = 'This email is already used by another user!';
      }
   }

   if(empty($message)){
      $update_profile = $conn->prepare("UPDATE `users` SET name = ?, email = ? WHERE id = ?");
      $update_profile->execute([$name, $email, $user_id]);

      $fetch_profile['name'] = $name;
      $fetch_profile['email'] = $email;

      $message[] = 'Profile updated successfully!';
   }

   // -------- Update image (optional) --------
   $old_image = (string)$fetch_profile['image'];

   if(isset($_FILES['image']) && !empty($_FILES['image']['name'])){

      $image_name = $_FILES['image']['name'];
      $image_size = (int)($_FILES['image']['size'] ?? 0);
      $image_tmp  = $_FILES['image']['tmp_name'] ?? '';

      $ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
      $allowed = ['jpg','jpeg','png'];

      if(!in_array($ext, $allowed, true)){
         $message[] = 'Image must be JPG, JPEG, or PNG!';
      } elseif($image_size > 2000000){
         $message[] = 'Image size is too large! Max 2MB.';
      } elseif(empty($image_tmp)){
         $message[] = 'Image upload failed!';
      } else {

         $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($image_name, PATHINFO_FILENAME));
         if($base === '') $base = 'img';

         $new_image = $base . '_' . time() . '.' . $ext;
         $image_folder = 'uploaded_img/' . $new_image;

         $update_image = $conn->prepare("UPDATE `users` SET image = ? WHERE id = ?");
         $ok = $update_image->execute([$new_image, $user_id]);

         if($ok){
            move_uploaded_file($image_tmp, $image_folder);

            // fshi foton e vjetër vetëm nëse NUK është default
            if(!empty($old_image) && $old_image !== 'default.png'){
               $old_path = 'uploaded_img/' . $old_image;
               if(file_exists($old_path)){
                  @unlink($old_path);
               }
            }

            $fetch_profile['image'] = $new_image;
            $message[] = 'Image updated successfully!';
         } else {
            $message[] = 'Image update failed!';
         }
      }
   }

   // -------- Update password (optional) --------
   $old_password_input = (string)($_POST['old_password'] ?? '');
   $new_password       = (string)($_POST['new_password'] ?? '');
   $confirm_password   = (string)($_POST['confirm_password'] ?? '');

   $wants_password_change = ($old_password_input !== '' || $new_password !== '' || $confirm_password !== '');

   if($wants_password_change){

      if($old_password_input === '' || $new_password === '' || $confirm_password === ''){
         $message[] = 'Fill all password fields to change password!';
      } elseif(strlen($new_password) < 8){
         $message[] = 'New password must be at least 8 characters!';
      } elseif($new_password !== $confirm_password){
         $message[] = 'Confirm password not matched!';
      } else {

         if(!password_verify($old_password_input, (string)$fetch_profile['password'])){
            $message[] = 'Old password not matched!';
         } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass_query = $conn->prepare("UPDATE `users` SET password = ? WHERE id = ?");
            $update_pass_query->execute([$new_hash, $user_id]);

            $fetch_profile['password'] = $new_hash;
            $message[] = 'Password updated successfully!';
         }
      }
   }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Update user profile</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/components.css">
</head>
<body>

<section class="update-profile">

   <h1 class="title">Update profile</h1>

   <form action="" method="POST" enctype="multipart/form-data">
      <img src="uploaded_img/<?= htmlspecialchars((string)$fetch_profile['image']); ?>" alt="">

      <div class="flex">
         <div class="inputBox">
            <span>Username:</span>
            <input type="text" name="name" value="<?= htmlspecialchars((string)$fetch_profile['name']); ?>" placeholder="update username" required class="box">

            <span>Email:</span>
            <input type="email" name="email" value="<?= htmlspecialchars((string)$fetch_profile['email']); ?>" placeholder="update email" required class="box">

            <span>Update picture:</span>
            <input type="file" name="image" accept="image/jpg, image/jpeg, image/png" class="box">
         </div>

         <div class="inputBox">
            <span>Old password:</span>
            <input type="password" name="old_password" placeholder="enter previous password" class="box">

            <span>New password:</span>
            <input type="password" name="new_password" placeholder="enter new password" class="box">

            <span>Confirm password:</span>
            <input type="password" name="confirm_password" placeholder="confirm new password" class="box">
         </div>
      </div>

      <div class="flex-btn">
         <input type="submit" class="btn" value="update profile" name="update_profile">
         <a href="home.php" class="option-btn">Go back</a>
      </div>
   </form>

</section>

<?php require_once __DIR__ . '/app/includes/footer.php'; ?>

<script src="js/script.js"></script>

</body>
</html>
