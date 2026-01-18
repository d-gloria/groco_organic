<?php
require_once __DIR__ . '/app/includes/header.php';

// user guard
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

$message = [];

if(isset($_POST['order'])){

   $name    = clean_text($_POST['name'] ?? '');
   $number  = clean_text($_POST['number'] ?? '');
   $email   = strtolower(clean_text($_POST['email'] ?? ''));
   $method  = clean_text($_POST['method'] ?? ''); // cash or paypal

   $flat    = clean_text($_POST['flat'] ?? '');
   $street  = clean_text($_POST['street'] ?? '');
   $city    = clean_text($_POST['city'] ?? '');
   $state   = clean_text($_POST['state'] ?? '');
   $country = clean_text($_POST['country'] ?? '');
   $pin     = clean_text($_POST['pin_code'] ?? '');

   // ------------------
   // VALIDATION
   // ------------------
   if($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100){
      $message[] = 'Name must be 2-100 characters!';
   }

   if($number === '' || !preg_match('/^[0-9+\s]{6,15}$/', $number)){
      $message[] = 'Phone number is not valid!';
   }

   if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100){
      $message[] = 'Email is not valid!';
   }

   // only 2 methods: cash + paypal (paypal includes paypal + card)
   $allowed_methods = ['cash','paypal'];
   if(!in_array($method, $allowed_methods, true)){
      $message[] = 'Payment method is not valid!';
   }

   if($flat === '' || $street === '' || $city === '' || $state === '' || $country === '' || $pin === ''){
      $message[] = 'Please fill all address fields!';
   }

   if($pin !== '' && !preg_match('/^[0-9]{3,10}$/', $pin)){
      $message[] = 'Postal code is not valid!';
   }

   // IMPORTANT: paypal should NOT be placed via normal submit
   if(empty($message) && $method === 'paypal'){
      $message[] = 'Please use the PayPal button to complete payment.';
   }

   // Build address
   $address = 'No. ' . $flat . ', ' . $street . ', ' . $city . ', ' . $state . ', ' . $country . ' - ' . $pin;

   // Cart totals
   $cart_total = 0;
   $cart_products = [];

   $cart_query = $conn->prepare("SELECT name, price, quantity FROM `cart` WHERE user_id = ?");
   $cart_query->execute([$user_id]);

   if($cart_query->rowCount() > 0){
      while($cart_item = $cart_query->fetch(PDO::FETCH_ASSOC)){
         $pname = (string)$cart_item['name'];
         $qty   = (int)$cart_item['quantity'];
         $price = (float)$cart_item['price'];

         if($qty < 1) { $qty = 1; }
         if($price < 0) { $price = 0; }

         $cart_products[] = $pname . ' ( ' . $qty . ' )';
         $cart_total += ($price * $qty);
      }
   }

   $total_products = implode(', ', $cart_products);
   $placed_on = date('d-M-Y');

   if(empty($message)){

      if($cart_total <= 0){
         $message[] = 'Your cart is empty.';
      }else{

         // prevent exact duplicate
         $order_query = $conn->prepare("
            SELECT id FROM `orders`
            WHERE user_id = ? AND name = ? AND number = ? AND email = ? AND method = ? AND address = ?
              AND total_products = ? AND total_price = ?
            LIMIT 1
         ");
         $order_query->execute([$user_id, $name, $number, $email, $method, $address, $total_products, (int)round($cart_total)]);

         if($order_query->rowCount() > 0){
            $message[] = 'Order placed already!';
         }else{

            // CASH orders are pending
            $payment_status = 'pending';
            $paid_at = null;

            $insert_order = $conn->prepare("
               INSERT INTO `orders`(
                  user_id, name, number, email,
                  method, payment_status, paid_at,
                  address, total_products, total_price, placed_on
               )
               VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ");

            $ok = $insert_order->execute([
               $user_id, $name, $number, $email,
               $method, $payment_status, $paid_at,
               $address, $total_products, (int)round($cart_total), $placed_on
            ]);

            if($ok){
               // clear cart for CASH (PayPal clears after capture)
               $delete_cart = $conn->prepare("DELETE FROM `cart` WHERE user_id = ?");
               $delete_cart->execute([$user_id]);
               $message[] = 'Order placed successfully!';
            }else{
               $message[] = 'Failed to place order. Try again!';
            }
         }
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
   <title>Checkout</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">

   <style>
      /* Bigger Payment Method UI */
      .payment-methods { margin-top: 18px; }
      .payment-title { font-weight: 700; margin: 0 0 12px; font-size: 16px; }
      .pm-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
      @media (min-width: 680px) { .pm-grid { grid-template-columns: 1fr 1fr; } }

      .pm-option {
         border: 1px solid rgba(0,0,0,.12);
         border-radius: 10px;
         padding: 14px 14px;
         display: flex;
         gap: 12px;
         align-items: flex-start;
         cursor: pointer;
         user-select: none;
         transition: transform .08s ease, border-color .12s ease, box-shadow .12s ease;
         background: #fff;
      }
      .pm-option:hover { transform: translateY(-1px); }
      .pm-option input[type="radio"] { margin-top: 3px; transform: scale(1.2); }
      .pm-main { display: flex; flex-direction: column; gap: 4px; }
      .pm-name { font-weight: 700; font-size: 15px; line-height: 1.2; }
      .pm-desc { opacity: .8; font-size: 13px; line-height: 1.35; }
      .pm-active { border-color: rgba(0,0,0,.35); box-shadow: 0 6px 18px rgba(0,0,0,.06); }

      /* PayPal area: stable layout + visible */
      #paypalWrap {
         margin-top: 16px;
         border: 1px solid rgba(0,0,0,.12);
         border-radius: 12px;
         padding: 14px;
         background: #fff;
      }
      #paypalWrap[aria-hidden="true"] { display:none; }
      #paypal-button-container { min-height: 44px; }
      #paypalMsg { margin-top: 10px; font-size: 13px; opacity: .85; }
   </style>
</head>
<body>

<?php
if(!empty($message)){
   foreach($message as $msg){
      echo '
      <div class="message">
         <span>'.htmlspecialchars($msg).'</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>';
   }
}
?>

<section class="display-orders">
   <?php
      $cart_grand_total = 0;
      $select_cart_items = $conn->prepare("SELECT * FROM `cart` WHERE user_id = ?");
      $select_cart_items->execute([$user_id]);

      if($select_cart_items->rowCount() > 0){
         while($fetch_cart_items = $select_cart_items->fetch(PDO::FETCH_ASSOC)){
            $price = (int)$fetch_cart_items['price'];
            $qty   = (int)$fetch_cart_items['quantity'];
            if($qty < 1) $qty = 1;

            $cart_total_price = $price * $qty;
            $cart_grand_total += $cart_total_price;
   ?>
   <p>
      <?= htmlspecialchars((string)$fetch_cart_items['name']); ?>
      <span>(<?= '$'.$price.'/- x '.$qty; ?>)</span>
   </p>
   <?php
         }
      }else{
         echo '<p class="empty">Your cart is empty!</p>';
      }
   ?>
   <div class="grand-total">Grand total: <span>$<?= (int)$cart_grand_total; ?>/-</span></div>
</section>

<section class="checkout-orders">
   <form id="checkoutForm" action="" method="POST">
      <h3>Checkout</h3>

      <div class="flex">
         <div class="inputBox">
            <span>Full name:</span>
            <input type="text" name="name" placeholder="Full name" class="box" required>
         </div>

         <div class="inputBox">
            <span>Phone number:</span>
            <input type="text" name="number" placeholder="Phone number" class="box" required>
         </div>

         <div class="inputBox">
            <span>Email:</span>
            <input type="email" name="email" placeholder="Email address" class="box" required>
         </div>

         <div class="inputBox">
            <span>Apartment / Flat no.:</span>
            <input type="text" name="flat" placeholder="Apartment / Flat" class="box" required>
         </div>

         <div class="inputBox">
            <span>Street / Area:</span>
            <input type="text" name="street" placeholder="Street / Area" class="box" required>
         </div>

         <div class="inputBox">
            <span>City:</span>
            <input type="text" name="city" placeholder="City" class="box" required>
         </div>

         <div class="inputBox">
            <span>State / Region:</span>
            <input type="text" name="state" placeholder="State / Region" class="box" required>
         </div>

         <div class="inputBox">
            <span>Country:</span>
            <input type="text" name="country" placeholder="Country" class="box" required>
         </div>

         <div class="inputBox">
            <span>Postal code:</span>
            <input type="text" name="pin_code" placeholder="Postal code" class="box" required>
         </div>
      </div>

      <!-- Payment options at end -->
      <div class="payment-methods">
         <p class="payment-title">Payment method</p>

         <div class="pm-grid">
            <label class="pm-option" id="pmPaypal">
               <input type="radio" name="method" value="paypal" id="payPaypal" required>
               <div class="pm-main">
                  <div class="pm-name">PayPal (PayPal + Card)</div>
                  <div class="pm-desc">Pay securely with PayPal. You can also pay by card via PayPal.</div>
               </div>
            </label>

            <label class="pm-option pm-active" id="pmCash">
               <input type="radio" name="method" value="cash" id="payCash" checked>
               <div class="pm-main">
                  <div class="pm-name">Cash on delivery</div>
                  <div class="pm-desc">Pay the courier when your order arrives.</div>
               </div>
            </label>
         </div>
      </div>

      <!-- Normal submit (cash only) -->
      <input id="normalSubmit" type="submit" name="order"
         class="btn <?= ($cart_grand_total > 0) ? '' : 'disabled'; ?>"
         value="Place order">

      <!-- PayPal area -->
      <div id="paypalWrap" aria-hidden="true">
         <p style="margin:0 0 10px; opacity:.85;">Pay with PayPal:</p>
         <div id="paypal-button-container"></div>
         <p id="paypalMsg"></p>
      </div>
   </form>
</section>

<?php require_once __DIR__ . '/app/includes/footer.php'; ?>

<script src="js/script.js"></script>

<!-- PayPal SDK -->
<script src="https://www.paypal.com/sdk/js?client-id=AfqLFGdYy2aYFkbgN7cQ_wfWUZ2OEMHQui3ZBAp-u31lQkOXRVKcndTGKtHG4e8C5W1oQrbJtBILCVs2&currency=USD&intent=capture&components=buttons"></script>

<script>
(function() {
  const paypalRadio  = document.getElementById('payPaypal');
  const cashRadio    = document.getElementById('payCash');
  const paypalWrap   = document.getElementById('paypalWrap');
  const normalSubmit = document.getElementById('normalSubmit');
  const form         = document.getElementById('checkoutForm');
  const msg          = document.getElementById('paypalMsg');

  const pmPaypal = document.getElementById('pmPaypal');
  const pmCash   = document.getElementById('pmCash');

  let rendered = false;

  function setActiveCard() {
    if (paypalRadio.checked) {
      pmPaypal.classList.add('pm-active');
      pmCash.classList.remove('pm-active');
    } else {
      pmCash.classList.add('pm-active');
      pmPaypal.classList.remove('pm-active');
    }
  }

  function showPaypal(show) {
    paypalWrap.setAttribute('aria-hidden', show ? 'false' : 'true');
    normalSubmit.style.display = show ? 'none' : 'inline-block';
    setActiveCard();
  }

  function ensureRender() {
    if (rendered) return;
    if (typeof paypal === 'undefined') {
      msg.textContent = 'PayPal SDK did not load. Refresh the page.';
      return;
    }

    paypal.Buttons({
      createOrder: function() {
        msg.textContent = '';
        const fd = new FormData(form);
        fd.set('method','paypal');

        return fetch('paypal_create_order.php', {
          method: 'POST',
          body: fd
        })
        .then(async r => {
          const j = await r.json().catch(() => ({}));
          if (!r.ok || !j.paypal_order_id) {
            const e = j && (j.message || j.error) ? (j.message || j.error) : 'create order failed';
            msg.textContent = 'PayPal error: ' + e;
            throw new Error(e);
          }
          return j.paypal_order_id;
        });
      },

      onApprove: function(data) {
        msg.textContent = 'Processing payment...';
        const fd = new FormData();
        fd.append('paypal_order_id', data.orderID);

        return fetch('paypal_capture_order.php', {
          method: 'POST',
          body: fd
        })
        .then(async r => {
          const j = await r.json().catch(() => ({}));
          if (!r.ok || !j.ok) {
            const e = j && (j.message || j.error) ? (j.message || j.error) : 'capture failed';
            msg.textContent = 'PayPal error: ' + e;
            throw new Error(e);
          }
          msg.textContent = 'Payment completed. Redirecting...';
          window.location.href = 'orders.php';
        });
      },

      onCancel: function() {
        msg.textContent = 'Payment was cancelled.';
      },

      onError: function(err) {
        console.error('PayPal onError:', err);
        msg.textContent = 'PayPal error: ' + (err && err.message ? err.message : 'Unknown error');
      }
    }).render('#paypal-button-container');

    rendered = true;
  }

  function toggle() {
    const isPaypal = paypalRadio.checked;
    showPaypal(isPaypal);
    if (isPaypal) {
      // IMPORTANT: container must be visible before render
      ensureRender();
      paypalWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
      msg.textContent = '';
    } else {
      msg.textContent = '';
    }
  }

  // prevent submit via Enter when PayPal selected
  form.addEventListener('submit', function(e) {
    if (paypalRadio.checked) {
      e.preventDefault();
      toggle();
      msg.textContent = 'Please use the PayPal button below to complete payment.';
      return false;
    }
  });

  paypalRadio.addEventListener('change', toggle);
  cashRadio.addEventListener('change', toggle);
  toggle();
})();
</script>

</body>
</html>
