<?php

@require_once __DIR__ . '/app/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

// Helpers
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
      // ignore
   }
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

// Identify who is logging out
$user_id  = $_SESSION['user_id'] ?? null;
$admin_id = $_SESSION['admin_id'] ?? null;

$actor_id = null;
if ($user_id !== null) $actor_id = (int)$user_id;
if ($actor_id === null && $admin_id !== null) $actor_id = (int)$admin_id;

// Get email for logs (optional)
$email = null;
if ($actor_id !== null) {
   try {
      $st = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
      $st->execute([$actor_id]);
      $r = $st->fetch(PDO::FETCH_ASSOC);
      $email = $r['email'] ?? null;
   } catch (Throwable $e) {}
}

// Revoke remember token in DB (if exists)
if (!empty($_COOKIE['remember'])) {
   $cookie = (string)$_COOKIE['remember'];
   $parts = explode(':', $cookie, 2);
   $selector = $parts[0] ?? '';

   if ($selector !== '') {
      try {
         $del = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
         $del->execute([$selector]);
      } catch (Throwable $e) {}
   }

   clear_remember_cookie();
}

// Log logout
log_auth_event($conn, $actor_id, 'LOGOUT', $email, 'User logged out');

// Clear all session variables
$_SESSION = [];
if (ini_get("session.use_cookies")) {
   $params = session_get_cookie_params();
   setcookie(
      session_name(),
      '',
      time() - 42000,
      $params["path"],
      $params["domain"],
      $params["secure"],
      $params["httponly"]
   );
}
session_unset();
session_destroy();

header('Location: login.php');
exit;
