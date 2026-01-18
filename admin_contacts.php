<?php

// Admin guard + remember me + idle timeout + logs
require_once __DIR__ . '/app/includes/admin_header.php';

// --------------------
// DELETE MESSAGE
// --------------------
if (isset($_GET['delete'])) {
   $delete_id = (int)($_GET['delete'] ?? 0);

   if ($delete_id > 0) {
      $delete_message = $conn->prepare("DELETE FROM `message` WHERE id = ?");
      $delete_message->execute([$delete_id]);
      $message[] = 'message deleted!';
   }

   header('Location: admin_contacts.php');
   exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Messages</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>

<section class="messages">

   <h1 class="title">Messages</h1>

   <div class="box-container">

   <?php
      $select_message = $conn->prepare("SELECT * FROM `message` ORDER BY id DESC");
      $select_message->execute();

      if ($select_message->rowCount() > 0) {
         while ($fetch_message = $select_message->fetch(PDO::FETCH_ASSOC)) {
   ?>
   <div class="box">
      <p> User id: <span><?= (int)$fetch_message['user_id']; ?></span> </p>
      <p> Name: <span><?= htmlspecialchars((string)$fetch_message['name']); ?></span> </p>
      <p> Number: <span><?= htmlspecialchars((string)$fetch_message['number']); ?></span> </p>
      <p> Email: <span><?= htmlspecialchars((string)$fetch_message['email']); ?></span> </p>
      <p> Message: <span><?= htmlspecialchars((string)$fetch_message['message']); ?></span> </p>
      <a href="admin_contacts.php?delete=<?= (int)$fetch_message['id']; ?>"
         onclick="return confirm('delete this message?');"
         class="delete-btn">
         Delete
      </a>
   </div>
   <?php
         }
      } else {
         echo '<p class="empty">you have no messages!</p>';
      }
   ?>

   </div>

</section>

<script src="js/script.js"></script>

</body>
</html>
