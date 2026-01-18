<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/paypal.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

// Turn this to false when everything works
define('PAYPAL_DEBUG', true);

function respond(int $code, array $data): void {
  http_response_code($code);
  echo json_encode($data);
  exit;
}

function clean_text($v): string {
  $v = trim((string)$v);
  $v = preg_replace('/\s+/', ' ', $v);
  return $v;
}

function must_defined(string $name): void {
  if (!defined($name) || constant($name) === '' || constant($name) === null) {
    respond(500, ['error' => 'CONFIG_MISSING', 'missing' => $name]);
  }
}

must_defined('PAYPAL_API_BASE');
must_defined('PAYPAL_CLIENT_ID');
must_defined('PAYPAL_SECRET');

function curl_json(string $url, array $opts, ?string &$raw = null, ?int &$http = null): array {
  $ch = curl_init($url);

  $defaults = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
  ];

  curl_setopt_array($ch, $defaults + $opts);

  $res = curl_exec($ch);
  $err = curl_error($ch);
  $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $raw = ($res === false) ? '' : (string)$res;
  $http = $httpCode;

  if ($res === false) {
    throw new Exception('CURL_ERROR: ' . $err);
  }

  $json = json_decode($raw, true);
  if (!is_array($json)) {
    throw new Exception('NON_JSON_RESPONSE (HTTP ' . $httpCode . '): ' . mb_substr($raw, 0, 400));
  }

  return $json;
}

function paypal_access_token(): string {
  $raw = null; $http = null;

  $json = curl_json(PAYPAL_API_BASE . '/v1/oauth2/token', [
    CURLOPT_POST => true,
    CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET,
    CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'Accept-Language: en_US'
    ],
  ], $raw, $http);

  if (($http ?? 500) >= 400 || empty($json['access_token'])) {
    throw new Exception('PAYPAL_TOKEN_ERROR (HTTP '.($http ?? 0).'): ' . ($raw ?? ''));
  }

  return (string)$json['access_token'];
}

function paypal_create_order_api(string $token, string $amount, string $currency = 'USD'): array {
  $payload = [
    'intent' => 'CAPTURE',
    'purchase_units' => [
      [
        'amount' => [
          'currency_code' => $currency,
          'value' => $amount
        ],
        'description' => 'Grocery Store Order'
      ]
    ]
  ];

  $raw = null; $http = null;

  $json = curl_json(PAYPAL_API_BASE . '/v2/checkout/orders', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $token,
      'Prefer: return=representation'
    ],
  ], $raw, $http);

  if (($http ?? 500) >= 400 || empty($json['id'])) {
    throw new Exception('PAYPAL_CREATE_ERROR (HTTP '.($http ?? 0).'): ' . ($raw ?? ''));
  }

  return $json;
}

// -------------------------
// Auth guard
// -------------------------
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) respond(401, ['error' => 'NOT_LOGGED_IN']);

// must be paypal
$method = (string)($_POST['method'] ?? '');
if ($method !== 'paypal') respond(400, ['error' => 'INVALID_METHOD']);

// -------------------------
// Read fields
// -------------------------
$name    = clean_text($_POST['name'] ?? '');
$number  = clean_text($_POST['number'] ?? '');
$email   = strtolower(clean_text($_POST['email'] ?? ''));

$flat    = clean_text($_POST['flat'] ?? '');
$street  = clean_text($_POST['street'] ?? '');
$city    = clean_text($_POST['city'] ?? '');
$state   = clean_text($_POST['state'] ?? '');
$country = clean_text($_POST['country'] ?? '');
$pin     = clean_text($_POST['pin_code'] ?? '');

// validation
$errors = [];
if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Name invalid';
if ($number === '' || !preg_match('/^[0-9+\s]{6,15}$/', $number)) $errors[] = 'Phone invalid';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) $errors[] = 'Email invalid';
if ($flat === '' || $street === '' || $city === '' || $state === '' || $country === '' || $pin === '') $errors[] = 'Address incomplete';
if ($pin !== '' && !preg_match('/^[0-9]{3,10}$/', $pin)) $errors[] = 'Postal code invalid';
if ($errors) respond(422, ['error' => 'VALIDATION', 'messages' => $errors]);

$address = 'No. ' . $flat . ', ' . $street . ', ' . $city . ', ' . $state . ', ' . $country . ' - ' . $pin;

// -------------------------
// Cart totals from DB (server truth)
// -------------------------
global $conn;

$cart_total = 0.0;
$cart_products = [];

$cart_query = $conn->prepare("SELECT name, price, quantity FROM `cart` WHERE user_id = ?");
$cart_query->execute([$user_id]);

if ($cart_query->rowCount() <= 0) respond(422, ['error' => 'EMPTY_CART']);

while ($item = $cart_query->fetch(PDO::FETCH_ASSOC)) {
  $pname = (string)$item['name'];
  $qty   = (int)$item['quantity'];
  $price = (float)$item['price'];

  if ($qty < 1) $qty = 1;
  if ($price < 0) $price = 0;

  $cart_products[] = $pname . ' ( ' . $qty . ' )';
  $cart_total += ($price * $qty);
}

if ($cart_total <= 0) respond(422, ['error' => 'EMPTY_CART']);

$total_products = implode(', ', $cart_products);

// store as int in DB like you already do
$total_price_int = (int)round($cart_total);

// PayPal amount: must be string with 2 decimals
$amount = number_format($cart_total, 2, '.', '');

// keep same format as your app (so it matches your DB)
$placed_on = date('d-M-Y');

// -------------------------
// Reuse a pending paypal order if exists (same day)
// -------------------------
$find = $conn->prepare("
  SELECT id
  FROM orders
  WHERE user_id = ?
    AND method = 'paypal'
    AND payment_status = 'pending'
    AND (paypal_order_id IS NULL OR paypal_order_id = '')
    AND total_price = ?
    AND placed_on = ?
  ORDER BY id DESC
  LIMIT 1
");
$find->execute([$user_id, $total_price_int, $placed_on]);
$existing = $find->fetch(PDO::FETCH_ASSOC);

if ($existing) {
  $db_order_id = (int)$existing['id'];

  $updForm = $conn->prepare("
    UPDATE orders
    SET name = ?, number = ?, email = ?, address = ?, total_products = ?
    WHERE id = ? AND user_id = ?
  ");
  $updForm->execute([$name, $number, $email, $address, $total_products, $db_order_id, $user_id]);

} else {
  // create pending order in DB first, don't clear cart
  $ins = $conn->prepare("
    INSERT INTO orders
      (user_id, name, number, email, method, address, total_products, total_price, placed_on, payment_status, paid_at)
    VALUES
      (?,?,?,?,?,?,?,?,?,'pending',NULL)
  ");
  $ins->execute([$user_id, $name, $number, $email, 'paypal', $address, $total_products, $total_price_int, $placed_on]);
  $db_order_id = (int)$conn->lastInsertId();
}

$_SESSION['pending_paypal_order_id'] = $db_order_id;

try {
  $token = paypal_access_token();
  $pp = paypal_create_order_api($token, $amount, 'USD');

  $paypal_order_id = (string)$pp['id'];

  // save paypal order id
  $upd = $conn->prepare("UPDATE orders SET paypal_order_id = ? WHERE id = ? AND user_id = ?");
  $upd->execute([$paypal_order_id, $db_order_id, $user_id]);

  respond(200, [
    'paypal_order_id' => $paypal_order_id,
    'db_order_id' => $db_order_id,
    'amount' => $amount
  ]);

} catch (Throwable $e) {
  // Don’t leak secrets; but show useful debug
  if (PAYPAL_DEBUG) {
    respond(500, ['error' => 'PAYPAL_ERROR', 'message' => $e->getMessage()]);
  }
  respond(500, ['error' => 'PAYPAL_ERROR']);
}
