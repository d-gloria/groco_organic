<?php
require_once __DIR__ . '/app/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$user_id = $_SESSION['verify_user_id'] ?? null;
$email   = $_SESSION['verify_email'] ?? null;

if (!$user_id || !$email) {
  header('location:register.php');
  exit;
}

$message = [];

if (isset($_POST['verify'])) {
  $code = trim($_POST['code'] ?? '');

  if (!preg_match('/^\d{6}$/', $code)) {
    $message[] = 'Enter a valid 6-digit code.';
  } else {
    $stmt = $conn->prepare("
      SELECT id, verify_code_hash, verify_expires_at, is_verified
      FROM users
      WHERE id = ? AND email = ?
      LIMIT 1
    ");
    $stmt->execute([(int)$user_id, $email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
      $message[] = 'User not found.';
    } elseif ((int)$u['is_verified'] === 1) {
      unset($_SESSION['verify_user_id'], $_SESSION['verify_email']);
      header('location:login.php');
      exit;
    } else {
      $expires = $u['verify_expires_at'] ? strtotime($u['verify_expires_at']) : 0;

      if ($expires && time() > $expires) {
        $message[] = 'Code expired. Please register again to get a new code.';
      } elseif (!password_verify($code, $u['verify_code_hash'] ?? '')) {
        $message[] = 'Incorrect code.';
      } else {
        $upd = $conn->prepare("
          UPDATE users
          SET is_verified = 1,
              verify_code_hash = NULL,
              verify_expires_at = NULL
          WHERE id = ?
        ");
        $upd->execute([(int)$user_id]);

        unset($_SESSION['verify_user_id'], $_SESSION['verify_email']);
        header('location:login.php');
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
  <title>Verify Email</title>
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
    <h3>Verify Email</h3>
    <p style="margin-bottom:10px;">
      We sent a code to <b><?php echo htmlspecialchars($email); ?></b>
    </p>
    <input type="text" name="code" class="box" placeholder="Enter 6-digit code" maxlength="6" required>
    <input type="submit" name="verify" value="Verify" class="btn">
  </form>
</section>

</body>
</html>
