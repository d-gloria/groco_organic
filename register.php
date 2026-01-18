<?php

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/libs/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

if(isset($_POST['submit'])){

   $name  = trim($_POST['name'] ?? '');
   $email = strtolower(trim($_POST['email'] ?? ''));
   $pass  = $_POST['pass'] ?? '';
   $cpass = $_POST['cpass'] ?? '';

   // ------------------
   // BACK-END VALIDATION
   // ------------------
   if($name === '' || strlen($name) < 2){
      $message[] = 'Name must be at least 2 characters!';
   }

   if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)){
      $message[] = 'Invalid email!';
   }

   if(strlen($pass) < 8){
      $message[] = 'Password must be at least 8 characters!';
   }

   if($pass !== $cpass){
      $message[] = 'Confirm password not matched!';
   }

   // ------------------
   // IMAGE (OPTIONAL)
   // ------------------
   $image_name = 'default.png';

   if(!empty($_FILES['image']['name'])){

      $img = $_FILES['image'];
      $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
      $allowed = ['jpg','jpeg','png'];

      if(!in_array($ext, $allowed)){
         $message[] = 'Image must be JPG, JPEG or PNG!';
      }

      if($img['size'] > 2000000){
         $message[] = 'Image size must be under 2MB!';
      }

      if(empty($message)){
         $image_name = time().'_'.$img['name'];
         move_uploaded_file($img['tmp_name'], 'uploaded_img/'.$image_name);
      }
   }

   // ------------------
   // INSERT USER (PENDING) + SEND VERIFICATION CODE
   // ------------------
   if(empty($message)){

      // Check if email exists
      $check = $conn->prepare("SELECT id, is_verified FROM users WHERE email = ? LIMIT 1");
      $check->execute([$email]);
      $existing = $check->fetch(PDO::FETCH_ASSOC);

      if($existing && (int)$existing['is_verified'] === 1){
         $message[] = 'Email already exists!';
      } else {

         $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);

         // 6-digit verification code
         $code = (string) random_int(100000, 999999);
         $code_hash = password_hash($code, PASSWORD_DEFAULT);

         $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
         $sent_at    = (new DateTime())->format('Y-m-d H:i:s');

         if(!$existing){
            // Insert new unverified user
            $insert = $conn->prepare(
               "INSERT INTO users 
                (name, email, password, image, is_verified, verify_code_hash, verify_expires_at, verify_sent_at)
                VALUES (?,?,?,?,0,?,?,?)"
            );
            $insert->execute([
               $name,
               $email,
               $hashed_pass,
               $image_name,
               $code_hash,
               $expires_at,
               $sent_at
            ]);

            $user_id = (int)$conn->lastInsertId();

         } else {
            // User exists but not verified → update & resend code
            $user_id = (int)$existing['id'];

            $upd = $conn->prepare(
               "UPDATE users
                SET name = ?, password = ?, image = ?, 
                    verify_code_hash = ?, verify_expires_at = ?, verify_sent_at = ?
                WHERE id = ? AND is_verified = 0"
            );
            $upd->execute([
               $name,
               $hashed_pass,
               $image_name,
               $code_hash,
               $expires_at,
               $sent_at,
               $user_id
            ]);
         }

         // Send verification email
         $sent = send_verification_email($email, $name, $code);

         if(!$sent){
            $message[] = 'Could not send verification email. Please try again.';
         } else {
            $_SESSION['verify_user_id'] = $user_id;
            $_SESSION['verify_email']   = $email;

            header('location:verify.php');
            exit;
         }
      }
   }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Register</title>
   <link rel="stylesheet" href="css/components.css">
</head>
<body>

<?php
if(isset($message)){
   foreach($message as $msg){
      echo '
      <div class="message">
         <span>'.htmlspecialchars($msg).'</span>
         <i onclick="this.parentElement.remove();">X</i>
      </div>';
   }
}
?>

<section class="form-container">

   <form action="" method="POST" enctype="multipart/form-data">
      <h3>Register</h3>

      <input type="text" name="name" class="box" placeholder="Name" required>
      <input type="email" name="email" class="box" placeholder="Email" required>
      <input type="password" name="pass" class="box" placeholder="Password" required>
      <input type="password" name="cpass" class="box" placeholder="Confirm password" required>

      <!-- FOTO OPSIONALE -->
      <!--<input type="file" name="image" class="box" accept="image/jpg,image/jpeg,image/png"> -->

      <input type="submit" name="submit" value="Register" class="btn">

      <p>Already have an account?<a href="login.php">Login</a></p>
   </form>

</section>

</body>
</html>
