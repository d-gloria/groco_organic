<?php
@require_once __DIR__ . '/app/config/config.php';
@require_once __DIR__ . '/app/config/paypal.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

function respond(int $code, array $data) {
  http_response_code($code);
  echo json_encode($data);
  exit;
}

function paypal_access_token(): string {
  $ch = curl_init(PAYPAL_API_BASE . '/v1/oauth2/token');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET,
    CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'Accept-Language: en_US'
    ]
  ]);
  $res = curl_exec($ch);
  if ($res === false) { curl_close($ch); throw new Exception('token fail'); }
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $json = json_decode($res, true);
  if ($code >= 400 || empty($json['access_token'])) throw new Exception('token error');
  return (string)$json['access_token'];
}

function paypal_capture(string $token, string $paypal_order_id): array {
  $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders/' . rawurlencode($paypal_order_id) . '/capture');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $token
    ]
  ]);
  $res = curl_exec($ch);
  if ($res === false) { curl_close($ch); throw new Exception('capture fail'); }
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $json = json_decode($res, true);
  if ($code >= 400) throw new Exception('capture error');
  return $json;
}

// Auth guard
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) respond(401, ['error' => 'NOT_LOGGED_IN']);
$user_id = (int)$user_id;

$paypal_order_id = (string)($_POST['paypal_order_id'] ?? '');
if ($paypal_order_id === '') respond(422, ['error' => 'MISSING_PAYPAL_ORDER_ID']);

// Find db order (prefer session, fallback by paypal_order_id)
$db_order_id = (int)($_SESSION['pending_paypal_order_id'] ?? 0);

if ($db_order_id > 0) {
  // validate it matches paypal_order_id
  $st = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND paypal_order_id = ? LIMIT 1");
  $st->execute([$db_order_id, $user_id, $paypal_order_id]);
  if (!(int)$st->fetchColumn()) {
    $db_order_id = 0;
  }
}

if ($db_order_id <= 0) {
  $st = $conn->prepare("SELECT id FROM orders WHERE user_id = ? AND paypal_order_id = ? LIMIT 1");
  $st->execute([$user_id, $paypal_order_id]);
  $db_order_id = (int)($st->fetchColumn() ?: 0);
}

if ($db_order_id <= 0) respond(422, ['error' => 'ORDER_NOT_FOUND']);

try {
  // If already paid, return OK (avoid double operations)
  $chk = $conn->prepare("SELECT payment_status FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
  $chk->execute([$db_order_id, $user_id]);
  $cur = (string)($chk->fetchColumn() ?: '');

  if ($cur === 'paid') {
    unset($_SESSION['pending_paypal_order_id']);
    respond(200, ['ok' => true, 'db_order_id' => $db_order_id, 'paypal_order_id' => $paypal_order_id]);
  }

  $token = paypal_access_token();
  $cap = paypal_capture($token, $paypal_order_id);

  $status = (string)($cap['status'] ?? '');
  if ($status !== 'COMPLETED') {
    respond(422, ['error' => 'NOT_COMPLETED', 'status' => $status]);
  }

  // capture id (nëse gjendet)
  $capture_id = null;
  if (!empty($cap['purchase_units'][0]['payments']['captures'][0]['id'])) {
    $capture_id = (string)$cap['purchase_units'][0]['payments']['captures'][0]['id'];
  }

  // Update DB order -> paid + paid_at
  $upd = $conn->prepare("
    UPDATE orders
    SET payment_status = 'paid',
        paypal_capture_id = ?,
        paid_at = NOW()
    WHERE id = ? AND user_id = ? AND paypal_order_id = ?
  ");
  $upd->execute([$capture_id, $db_order_id, $user_id, $paypal_order_id]);

  // Clear cart only after successful capture
  $del = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
  $del->execute([$user_id]);

  unset($_SESSION['pending_paypal_order_id']);

  respond(200, [
    'ok' => true,
    'db_order_id' => $db_order_id,
    'paypal_order_id' => $paypal_order_id,
    'paypal_capture_id' => $capture_id
  ]);

} catch (Throwable $e) {
  respond(500, ['error' => 'PAYPAL_ERROR']);
}
