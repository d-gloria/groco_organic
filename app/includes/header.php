<?php

@require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

// -------------------------------
// Helpers (auth logs + remember)
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
      // mos e prish aplikacionin nëse log-u dështon
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

function set_remember_cookie(string $selector, string $validator, int $expire_ts): void {
   $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
   setcookie('remember', $selector . ':' . $validator, [
      'expires'  => $expire_ts,
      'path'     => '/',
      'domain'   => '',
      'secure'   => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
   ]);
}

function do_logout_and_redirect(PDO $conn, string $reason_event = 'LOGOUT'): void {
   $uid = $_SESSION['user_id'] ?? null;
   $aid = $_SESSION['admin_id'] ?? null;
   $who = $uid ? (int)$uid : ($aid ? (int)$aid : null);

   if ($who) {
      $email = null;
      try {
         $st = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
         $st->execute([$who]);
         $r = $st->fetch(PDO::FETCH_ASSOC);
         $email = $r['email'] ?? null;
      } catch (Throwable $e) {}

      log_auth_event($conn, $who, $reason_event, $email, 'Session ended');
   }

   // fshi remember token në DB nëse ekziston selector në cookie
   if (!empty($_COOKIE['remember'])) {
      $parts = explode(':', (string)$_COOKIE['remember'], 2);
      $selector = $parts[0] ?? '';
      if ($selector !== '') {
         try {
            $del = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $del->execute([$selector]);
         } catch (Throwable $e) {}
      }
      clear_remember_cookie();
   }

   // shkatërro session
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
   session_destroy();

   header('Location: login.php');
   exit;
}

// -------------------------------
// 1) Auto-login with Remember Me
// -------------------------------
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id']) && !empty($_COOKIE['remember'])) {
   $cookie = (string)$_COOKIE['remember'];
   $parts = explode(':', $cookie, 2);

   if (count($parts) === 2) {
      $selector = $parts[0];
      $validator = $parts[1];

      try {
         $stmt = $conn->prepare("
            SELECT rt.user_id, rt.validator_hash, rt.expires_at, u.email, u.user_type
            FROM remember_tokens rt
            INNER JOIN users u ON u.id = rt.user_id
            WHERE rt.selector = ?
            LIMIT 1
         ");
         $stmt->execute([$selector]);
         $tok = $stmt->fetch(PDO::FETCH_ASSOC);

         if ($tok) {
            $expires_at = $tok['expires_at'] ?? null;
            $is_expired = $expires_at ? (strtotime($expires_at) < time()) : true;

            $validator_hash = hash('sha256', $validator);
            $db_hash = (string)($tok['validator_hash'] ?? '');

            if (!$is_expired && hash_equals($db_hash, $validator_hash)) {
               // sukses: krijo session
               session_regenerate_id(true);

               unset($_SESSION['admin_id'], $_SESSION['user_id']);
               $_SESSION['last_activity'] = time();

               $uid = (int)$tok['user_id'];
               $role = (string)($tok['user_type'] ?? 'user');

               if ($role === 'admin') {
                  $_SESSION['admin_id'] = $uid;
               } else {
                  $_SESSION['user_id'] = $uid;
               }

               // rotation i token-it për siguri (profesori e kërkon)
               $new_selector  = bin2hex(random_bytes(12));
               $new_validator = bin2hex(random_bytes(32));
               $new_vhash     = hash('sha256', $new_validator);
               $expire_ts     = time() + (30 * 24 * 60 * 60);
               $new_expires   = date('Y-m-d H:i:s', $expire_ts);

               // UPDATE me selector-in aktual (jo me user_id) -> më korrekt
               $upd = $conn->prepare("
                  UPDATE remember_tokens
                  SET selector = ?, validator_hash = ?, expires_at = ?
                  WHERE selector = ?
                  LIMIT 1
               ");
               $upd->execute([$new_selector, $new_vhash, $new_expires, $selector]);

               set_remember_cookie($new_selector, $new_validator, $expire_ts);

               log_auth_event($conn, $uid, 'REMEMBER_LOGIN', $tok['email'] ?? null, 'Auto-login via remember me');

            } else {
               // token invalid ose expired: fshi DB + cookie
               try {
                  $del = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                  $del->execute([$selector]);
               } catch (Throwable $e) {}
               clear_remember_cookie();
            }
         } else {
            clear_remember_cookie();
         }
      } catch (Throwable $e) {
         // ignore
      }
   } else {
      clear_remember_cookie();
   }
}

// -------------------------------
// 2) Idle timeout 15 minutes
// -------------------------------
$IDLE_LIMIT_SECONDS = 15 * 60;

$has_auth_session = (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']));

if ($has_auth_session) {
   $last = (int)($_SESSION['last_activity'] ?? 0);
   if ($last > 0 && (time() - $last) > $IDLE_LIMIT_SECONDS) {
      do_logout_and_redirect($conn, 'IDLE_LOGOUT');
   }
   $_SESSION['last_activity'] = time();
}

// -------------------------------
// 3) Redirect if trying to access protected pages while not logged in
// -------------------------------
$current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');

$protected_pages = [
   'cart.php', 'wishlist.php', 'checkout.php', 'orders.php', 'user_profile_update.php', 'contact.php'
];

if (!$has_auth_session && in_array($current, $protected_pages, true)) {
   header('Location: login.php');
   exit;
}

// -------------------------------
// Mesazhe (nëse ka)
// -------------------------------
if (isset($message) && is_array($message)) {
   foreach ($message as $msg) {
      echo '
      <div class="message">
         <span>' . htmlspecialchars((string)$msg) . '</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>';
   }
}

// -------------------------------
// Normal header data (profile + counts)
// -------------------------------
$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = $user_id !== null;

$cart_count = 0;
$wishlist_count = 0;
$fetch_profile = null;

if ($is_logged_in) {
   $uid = (int)$user_id;

   $count_cart_items = $conn->prepare("SELECT COUNT(*) FROM `cart` WHERE user_id = ?");
   $count_cart_items->execute([$uid]);
   $cart_count = (int)$count_cart_items->fetchColumn();

   $count_wishlist_items = $conn->prepare("SELECT COUNT(*) FROM `wishlist` WHERE user_id = ?");
   $count_wishlist_items->execute([$uid]);
   $wishlist_count = (int)$count_wishlist_items->fetchColumn();

   $select_profile = $conn->prepare("SELECT name, image, user_type FROM `users` WHERE id = ? LIMIT 1");
   $select_profile->execute([$uid]);
   $fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);

   if ($fetch_profile && empty($fetch_profile['image'])) {
      $fetch_profile['image'] = 'default.png';
   }
}

?>
<header class="header">

   <div class="flex">

      <a href="home.php" class="logo">Groco<span>.</span></a>

      <nav class="navbar">
         <a href="home.php">Home</a>
         <a href="shop.php">Shop</a>

         <?php if ($is_logged_in): ?>
            <a href="orders.php">Orders</a>
            <a href="contact.php">Contact</a>
         <?php else: ?>
            <a href="about.php">About</a>
         <?php endif; ?>
      </nav>

      <div class="icons">
         <div id="menu-btn" class="fas fa-bars"></div>
         <div id="user-btn" class="fas fa-user"></div>
         <a href="search_page.php" class="fas fa-search"></a>

         <?php if ($is_logged_in): ?>
            <a href="wishlist.php">
               <i class="fas fa-heart"></i>
               <span>(<?= $wishlist_count; ?>)</span>
            </a>
            <a href="cart.php">
               <i class="fas fa-shopping-cart"></i>
               <span>(<?= $cart_count; ?>)</span>
            </a>
         <?php endif; ?>
      </div>

      <div class="profile">

         <?php if ($is_logged_in && $fetch_profile): ?>

            <img src="uploaded_img/<?= htmlspecialchars((string)$fetch_profile['image']); ?>" alt="">
            <p><?= htmlspecialchars((string)$fetch_profile['name']); ?></p>
            <small style="opacity:.7;">role: <?= htmlspecialchars((string)$fetch_profile['user_type']); ?></small>

            <a href="user_profile_update.php" class="btn">Update profile</a>

            <?php if (($fetch_profile['user_type'] ?? '') === 'admin'): ?>
               <a href="admin_page.php" class="option-btn">Admin panel</a>
            <?php endif; ?>

            <a href="logout.php" class="delete-btn">Logout</a>

         <?php else: ?>

            <p>Please login or register.</p>
            <div class="flex-btn">
               <a href="login.php" class="option-btn">Login</a>
               <a href="register.php" class="option-btn">Register</a>
            </div>

         <?php endif; ?>

      </div>

   </div>

</header>
