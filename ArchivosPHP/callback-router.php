<?php
// /prueba/php/callback-router.php
session_start();
$cfg = require __DIR__ . '/bridge-config.php';
$secret     = $cfg['secret']     ?? '';
$notifyUrl  = $cfg['notify_url'] ?? '';
$preferWoo  = !empty($cfg['prefer_wc_thankyou']);
$brand      = $cfg['brand_name'] ?? 'Pago Seguro';
$logo       = $cfg['brand_logo'] ?? '';

if (!$secret || !$notifyUrl) { http_response_code(500); echo 'bridge-config.php incompleto (secret/notify_url).'; exit; }

$order_id   = $_SESSION['wc_order_id']  ?? '';
$order_key  = $_SESSION['wc_order_key'] ?? '';
$controlNum = $_SESSION['wc_controlNum']?? '';
$amount     = $_SESSION['wc_amount']    ?? '';
$returnWC   = $_SESSION['wc_returnWC']  ?? '';

// Mapear status (ajusta si conoces tus campos exactos)
$banorte_status_code = $_REQUEST['status'] ?? $_REQUEST['cd_response'] ?? $_REQUEST['code'] ?? '';
$auth_code = $_REQUEST['auth'] ?? $_REQUEST['authCode'] ?? $_REQUEST['authorization'] ?? '';
$txn_id    = $_REQUEST['txnid'] ?? $_REQUEST['id_trans'] ?? $_REQUEST['transactionId'] ?? '';
$message   = $_REQUEST['msg'] ?? $_REQUEST['message'] ?? $_REQUEST['desc'] ?? '';

$status = 'failed';
if ($banorte_status_code === '00' || strtoupper($banorte_status_code) === 'A'
    || stripos($message, 'aprob') !== false || stripos($message, 'approved') !== false) {
  $status = 'approved';
} elseif (stripos($message, 'pend') !== false || stripos($message, 'hold') !== false) {
  $status = 'pending';
}

// Notificar a Woo si hay datos
if ($order_id && $order_key) {
  $notify = [
    'order_id'      => (int)$order_id,
    'order_key'     => $order_key,
    'status'        => $status,
    'txn_id'        => $txn_id,
    'auth_code'     => $auth_code,
    'message'       => $message,
    'controlNumber' => $controlNum,
    'amount'        => $amount,
  ];
  $b64 = base64_encode(json_encode($notify, JSON_UNESCAPED_SLASHES));
  $sig = hash_hmac('sha256', $b64, $secret);

  $ch = curl_init($notifyUrl);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => ['payload' => $b64, 'sig' => $sig],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
  ]);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
}

// Si preferimos la "Thank You" de Woo y la tenemos, muéstrala con UX amistosa
if ($preferWoo && !empty($returnWC)) {
  $dest = $returnWC;
  ?><!doctype html>
  <html lang="es"><head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Confirmando tu pago…</title>
    <meta http-equiv="refresh" content="1;url=<?php echo htmlspecialchars($dest, ENT_QUOTES); ?>">
    <link rel="stylesheet" href="./assets/redirect.css">
  </head><body>
    <div class="wrap"><div class="card">
      <?php if ($logo): ?><img src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($brand, ENT_QUOTES); ?>" style="height:40px;margin-bottom:12px"/><?php endif; ?>
      <div class="spinner"></div>
      <h1>¡Pago procesado!</h1>
      <p>Estamos llevando tu pedido a la página de confirmación.</p>
      <p><a class="btn" href="<?php echo htmlspecialchars($dest, ENT_QUOTES); ?>">Ver confirmación ahora</a></p>
    </div></div>
    <script>setTimeout(function(){ location.href=<?php echo json_encode($dest); ?> }, 800);</script>
  </body></html><?php
  exit;
}

// Si no, conserva tu callback original
require __DIR__ . '/callback.php';
