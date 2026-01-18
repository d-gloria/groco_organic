<?php

@require_once __DIR__ . '/app/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

// -------------------------------
// Helpers
// -------------------------------
function client_ip(): string {
   if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
      return trim($parts[0]);
   }
   return $_SERVER['REMOTE_ADDR'] ?? '';
}

function user_agent(): string {
   $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
   return mb_substr($ua, 0, 255);
}

function log_auth_event(PDO $conn, ?int $user_id, string $event, ?string $email_entered, ?string $details = null): void {
   try {
      $stmt = $conn->prepare("
         INSERT INTO auth_logs (user_id, event, email_entered, ip_address, user_agent, details, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())
      ");
      $stmt->execute([
         $user_id,
         $event,
         $email_entered,
         client_ip(),
         user_agent(),
         $details
      ]);
   } catch (Throwable $e) {
      // Mos e prish login-in nëse log-u dështon
   }
}

function record_login_attempt(PDO $conn, ?int $user_id, string $email_entered, bool $success, ?string $reason = null): void {
   try {
      $stmt = $conn->prepare("
         INSERT INTO login_attempts (user_id, email_entered, ip_address, user_agent, success, reason, attempted_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())
      ");
      $stmt->execute([
         $user_id,
         $email_entered,
         client_ip(),
         user_agent(),
         $success ? 1 : 0,
         $reason
      ]);
   } catch (Throwable $e) {
      // ignore
   }
}

function failed_attempts_last_30_min(PDO $conn, string $email_entered): array {
   // kthen: [count_failed, last_failed_at]
   $stmt = $conn->prepare("
      SELECT 
         COUNT(*) AS cnt,
         MAX(attempted_at) AS last_failed_at
      FROM login_attempts
      WHERE email_entered = ?
        AND success = 0
        AND attempted_at >= (NOW() - INTERVAL 30 MINUTE)
   ");
   $stmt->execute([$email_entered]);
   $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'last_failed_at' => null];

   return [(int)($row['cnt'] ?? 0), $row['last_failed_at'] ?? null];
}

function is_blocked(PDO $conn, string $email_entered): array {
   // rregulli: pas 7 tentativash të pasuksesshme, blloko për 30 minuta.
   // Implementim: nëse në 30 minutat e fundit ka >=7 failed, blloko deri 30 min pas failed të fundit.
   [$cnt, $last_failed_at] = failed_attempts_last_30_min($conn, $email_entered);

   if ($cnt < 7 || !$last_failed_at) {
      return [false, null];
   }

   $blocked_until = date('Y-m-d H:i:s', strtotime($last_failed_at . ' +30 minutes'));
   $now = date('Y-m-d H:i:s');

   if ($now < $blocked_until) {
      return [true, $blocked_until];
   }

   return [false, null];
}

function clear_failed_attempts(PDO $conn, string $email_entered): void {
   // Reset i tentativave të gabuara pas login-it me sukses
   try {
      $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email_entered = ? AND success = 0");
      $stmt->execute([$email_entered]);
   } catch (Throwable $e) {
      // ignore
   }
}

function set_remember_cookie(string $selector, string $validator, int $expire_ts): void {
   $value = $selector . ':' . $validator;

   $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
   setcookie('remember', $value, [
      'expires'  => $expire_ts,
      'path'     => '/',
      'domain'   => '',
      'secure'   => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
   ]);
}

function clear_remember_cookie(): void {
   setcookie('remember', '', [
      'expires'  => time() - 3600,
      'path'     => '/',
      'domain'   => '',
      'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
      'httponly' => true,
      'samesite' => 'Lax',
   ]);
}

// -------------------------------
// Main
// -------------------------------
$message = [];

if (isset($_POST['submit'])) {

   $email = strtolower(trim((string)($_POST['email'] ?? '')));
   $pass  = (string)($_POST['pass'] ?? '');
   $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

   // Back-end validation
   if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $message[] = 'Please enter a valid email!';
   }
   if ($pass === '') {
      $message[] = 'Please enter your password!';
   }

   // Lockout check (para se të verifikojmë password)
   if (empty($message)) {
      [$blocked, $blocked_until] = is_blocked($conn, $email);
      if ($blocked) {
         record_login_attempt($conn, null, $email, false, 'blocked');
         log_auth_event($conn, null, 'LOGIN_BLOCKED', $email, 'Blocked until: ' . $blocked_until);
         $message[] = 'Too many failed attempts. Try again after ' . htmlspecialchars($blocked_until) . '.';
      }
   }

   if (empty($message)) {

      // Merr user vetëm nga email
      $stmt = $conn->prepare("SELECT id, email, password, user_type FROM `users` WHERE email = ? LIMIT 1");
      $stmt->execute([$email]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($row && !empty($row['password'])) {

         $uid = (int)$row['id'];

         if (password_verify($pass, $row['password'])) {

            // Refresh hash nëse duhet
            if (password_needs_rehash($row['password'], PASSWORD_DEFAULT)) {
               $newHash = password_hash($pass, PASSWORD_DEFAULT);
               $upd = $conn->prepare("UPDATE `users` SET password = ? WHERE id = ?");
               $upd->execute([$newHash, $uid]);
            }

            // Reset failed attempts (kërkesa e profesorit)
            clear_failed_attempts($conn, $email);

            // Log sukses + attempt sukses
            record_login_attempt($conn, $uid, $email, true, 'success');
            log_auth_event($conn, $uid, 'LOGIN_SUCCESS', $email, 'Role: ' . ($row['user_type'] ?? ''));

            // Session hardening
            session_regenerate_id(true);

            // Pastro sessionin për mos me pas konflikt
            unset($_SESSION['admin_id'], $_SESSION['user_id']);

            // Ruaj last activity (për 15-min idle timeout që do bëjmë te header/admin_header)
            $_SESSION['last_activity'] = time();

            // -------------------------------
            // Remember me (token rotation on login)
            // -------------------------------
            if ($remember) {
               try {
                  // Revoko token-at e vjetër për këtë user (thjeshtësi + siguri)
                  $del = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                  $del->execute([$uid]);

                  $selector  = bin2hex(random_bytes(12)); // 24 chars
                  $validator = bin2hex(random_bytes(32)); // 64 chars
                  $vhash     = hash('sha256', $validator);

                  $expire_ts = time() + (30 * 24 * 60 * 60); // 30 ditë
                  $expires_at = date('Y-m-d H:i:s', $expire_ts);

                  $ins = $conn->prepare("
                     INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at, created_at)
                     VALUES (?, ?, ?, ?, NOW())
                  ");
                  $ins->execute([$uid, $selector, $vhash, $expires_at]);

                  set_remember_cookie($selector, $validator, $expire_ts);

                  log_auth_event($conn, $uid, 'REMEMBER_TOKEN_ISSUED', $email, 'Remember me enabled');
               } catch (Throwable $e) {
                  // Nëse dështoi remember, mos e blloko login-in
                  clear_remember_cookie();
                  log_auth_event($conn, $uid, 'REMEMBER_TOKEN_ERROR', $email, 'Failed to issue remember token');
               }
            } else {
               // Nëse s’e do remember, pastro cookie
               if (!empty($_COOKIE['remember'])) {
                  clear_remember_cookie();
               }
            }

            // -------------------------------
            // Role based redirect
            // -------------------------------
            if (($row['user_type'] ?? '') === 'admin') {
               $_SESSION['admin_id'] = $uid;
               header('location:admin_users.php');
               exit;
            }

            if (($row['user_type'] ?? '') === 'user') {
               $_SESSION['user_id'] = $uid;
               header('location:user_profile_update.php');
               exit;
            }

            // fallback
            log_auth_event($conn, $uid, 'LOGIN_FAIL', $email, 'Invalid role');
            $message[] = 'Invalid user role!';

         } else {
            // Wrong password
            record_login_attempt($conn, $uid, $email, false, 'invalid_password');
            log_auth_event($conn, $uid, 'LOGIN_FAIL', $email, 'Invalid password');

            // në fund shfaq mesazh standard
            $message[] = 'incorrect email or password!';
         }

      } else {
         // No user
         record_login_attempt($conn, null, $email, false, 'no_user');
         log_auth_event($conn, null, 'LOGIN_FAIL', $email, 'No user found');
         $message[] = 'incorrect email or password!';
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
   <title>Login</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/components.css">
</head>
<body>

<?php
if (!empty($message)) {
   foreach ($message as $msg) {
      echo '
      <div class="message">
         <span>' . htmlspecialchars($msg) . '</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
      ';
   }
}
?>

<section class="form-container">

   <form action="" method="POST" novalidate>
      <h3>Login now</h3>

      <input
         type="email"
         name="email"
         class="box"
         placeholder="enter your email"
         required
         autocomplete="email"
         value="<?php echo isset($_POST['email']) ? htmlspecialchars((string)$_POST['email']) : ''; ?>"
      >

      <input
         type="password"
         name="pass"
         class="box"
         placeholder="enter your password"
         required
         autocomplete="current-password"
      >

      <label style="display:flex;align-items:center;gap:8px;margin:10px 0;">
         <input type="checkbox" name="remember" value="1">
         <span>Remember me</span>
      </label>

      <input type="submit" value="login now" class="btn" name="submit">

      <p style="margin-top:10px;">
         <a href="forgot_password.php">Forgot password?</a>
      </p>

      <p>Don't have an account? <a href="register.php">Register now</a></p>
   </form>

</section>

</body>
</html>
