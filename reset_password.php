<?php
require_once __DIR__ . '/app/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$message = [];
$token = $_GET['token'] ?? '';

if ($token === '' || !is_string($token)) {
  $message[] = 'Invalid or missing reset token.';
} else {

  // Hash token-in që vjen nga URL
  $token_hash = hash('sha256', $token);

  // Gjej user me token valid
  $stmt = $conn->prepare("
    SELECT id, reset_token_expires
    FROM users
    WHERE reset_token_hash = ?
    LIMIT 1
  ");
  $stmt->execute([$token_hash]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    $message[] = 'Invalid or expired reset link.';
  } else {
    $expires = $user['reset_token_expires']
      ? strtotime($user['reset_token_expires'])
      : 0;

    if ($expires && time() > $expires) {
      $message[] = 'Reset link has expired.';
    }
  }
}

if (isset($_POST['submit']) && empty($message)) {

  $pass  = $_POST['pass'] ?? '';
  $cpass = $_POST['cpass'] ?? '';

  if (strlen($pass) < 8) {
    $message[] = 'Password must be at least 8 characters!';
  }

  if ($pass !== $cpass) {
    $message[] = 'Confirm password not matched!';
  }

  if (empty($message)) {
    $new_hash = password_hash($pass, PASSWORD_DEFAULT);

    // Update password + pastro token-in
    $upd = $conn->prepare("
      UPDATE users
      SET password = ?,
          reset_token_hash = NULL,
          reset_token_expires = NULL
      WHERE id = ?
    ");
    $upd->execute([$new_hash, (int)$user['id']]);

    // Optional: forco logout nga sesionet ekzistuese (nëse ke)
    // (ti ke already security të mirë, kështu që është OK)

    header('location:login.php');
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <link rel="stylesheet" href="css/components.css">
</head>
<body>

<?php
if (!empty($message)) {
  foreach ($message as $msg) {
    echo '
    <div class="message">
      <span>' . htmlspecialchars($msg) . '</span>
      <i onclick="this.parentElement.remove();">X</i>
    </div>';
  }
}
?>

<?php if (empty($message) || isset($_POST['submit'])): ?>
<section class="form-container">
  <form method="POST">
    <h3>Reset Password</h3>

    <input
      type="password"
      name="pass"
      class="box"
      placeholder="New password"
      required
    >

    <input
      type="password"
      name="cpass"
      class="box"
      placeholder="Confirm new password"
      required
    >

    <input type="submit" name="submit" value="Reset password" class="btn">
  </form>
</section>
<?php endif; ?>

</body>
</html>
