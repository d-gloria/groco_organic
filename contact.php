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

// Helper
function clean_text($v) {
   $v = trim((string)$v);
   $v = preg_replace('/\s+/', ' ', $v);
   return $v;
}

if(isset($_POST['send'])){

   $name   = clean_text($_POST['name'] ?? '');
   $email  = strtolower(clean_text($_POST['email'] ?? ''));
   $number = clean_text($_POST['number'] ?? '');
   $msg    = clean_text($_POST['msg'] ?? '');

   // Back-end validation
   if($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100){
      $message[] = 'Name must be 2-100 characters!';
   }

   if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100){
      $message[] = 'Email is not valid!';
   }

   // phone: vetëm numra + hapësirë + plus
   if($number === '' || !preg_match('/^[0-9+\s]{6,15}$/', $number)){
      $message[] = 'Phone number is not valid!';
   }

   if($msg === '' || mb_strlen($msg) < 3 || mb_strlen($msg) > 500){
      $message[] = 'Message must be 3-500 characters!';
   }

   if(empty($message)){
      $select_message = $conn->prepare("
         SELECT id FROM `message`
         WHERE user_id = ? AND name = ? AND email = ? AND number = ? AND message = ?
         LIMIT 1
      ");
      $select_message->execute([$user_id, $name, $email, $number, $msg]);

      if($select_message->rowCount() > 0){
         $message[] = 'already sent message!';
      }else{
         $insert_message = $conn->prepare("INSERT INTO `message`(user_id, name, email, number, message) VALUES(?,?,?,?,?)");
         $insert_message->execute([$user_id, $name, $email, $number, $msg]);
         $message[] = 'sent message successfully!';
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
   <title>Contact</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
</head>
<body>

<section class="contact">

   <h1 class="title">Get in touch</h1>

   <form action="" method="POST" novalidate>
      <input type="text" name="name" class="box" required placeholder="enter your name" minlength="2" maxlength="100">
      <input type="email" name="email" class="box" required placeholder="enter your email" maxlength="100">
      <input type="text" name="number" class="box" required placeholder="enter your number" minlength="6" maxlength="15">
      <textarea name="msg" class="box" required placeholder="enter your message" cols="30" rows="10" minlength="3" maxlength="500"></textarea>
      <input type="submit" value="send message" class="btn" name="send">
   </form>

</section>

<?php require_once __DIR__ . '/app/includes/footer.php'; ?>

<script src="js/script.js"></script>

</body>
</html>
