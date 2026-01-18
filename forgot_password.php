<?php
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/libs/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$message = [];

if (isset($_POST['submit'])) {
  $email = strtolower(trim((string)($_POST['email'] ?? '')));

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message[] = 'Please enter a valid email!';
  } else {

    // Gjej user-in (mos zbulo nëse ekziston apo jo)
    $stmt = $conn->prepare("SELECT id, name, email, is_verified FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($u && (int)($u['is_verified'] ?? 0) === 1) {

      // Token i sigurt për link
      $token = bin2hex(random_bytes(32)); // 64 chars
      $token_hash = hash('sha256', $token);

      $expires = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');

      $upd = $conn->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires = ? WHERE id = ?");
      $upd->execute([$token_hash, $expires, (int)$u['id']]);

      // Link-u (localhost)
      $reset_link = 'http://localhost/grocery_store/reset_password.php?token=' . urlencode($token);

      // Dërgo email (përdor mailer.php që ke)
      $bodyCode = "Reset link: " . $reset_link; // fallback

      // Përdorim funksionin ekzistues send_verification_email si email generic?
      // Më mirë: dërgojmë si "code" tekstin e linkut - POR më mirë të bëjmë funksion të ri.
      // Për këtë hap, e dërgojmë si "code" vetëm për testim.
      $sent = send_verification_email($u['email'], $u['name'] ?? 'User', $reset_link);

      if ($sent) {
        // mesazh neutral
        $message[] = 'If that email exists, we sent a password reset link.';
      } else {
        $message[] = 'Could not send reset email. Please try again.';
      }

    } else {
      // gjithmonë mesazh neutral (security)
      $message[] = 'If that email exists, we sent a password reset link.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <link rel="stylesheet" href="css/components.css">
</head>
<body>

<?php
if(!empty($message)){
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
  <form method="POST">
    <h3>Forgot password</h3>
    <input type="email" name="email" class="box" placeholder="enter your email" required>
    <input type="submit" name="submit" value="Send reset link" class="btn">
    <p>Back to <a href="login.php">Login</a></p>
  </form>
</section>

</body>
</html>
