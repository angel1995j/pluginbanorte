<?php
// /prueba/php/bridge.php
session_start();
$cfg = require __DIR__ . '/bridge-config.php';
$secret    = $cfg['secret']     ?? '';
$notifyUrl = $cfg['notify_url'] ?? '';
$brand     = $cfg['brand_name'] ?? 'Pago Seguro';
$logo      = $cfg['brand_logo'] ?? '';

if (!$secret || !$notifyUrl) { http_response_code(500); echo 'bridge-config.php incompleto (secret/notify_url).'; exit; }

// Validar payload firmado
$payloadB64 = $_GET['payload'] ?? '';
$sig        = $_GET['sig']     ?? '';
if (!$payloadB64 || !$sig) { http_response_code(400); echo 'Faltan parámetros'; exit; }
$calc = hash_hmac('sha256', $payloadB64, $secret);
if (!hash_equals($calc, $sig)) { http_response_code(403); echo 'Firma inválida'; exit; }
$data = json_decode(base64_decode($payloadB64), true);
if (!is_array($data)) { http_response_code(400); echo 'Payload inválido'; exit; }

// Guardar en sesión
$_SESSION['wc_notifyUrl']  = $data['notifyUrl']  ?? $notifyUrl;
$_SESSION['wc_returnWC']   = $data['returnWC']   ?? '';
$_SESSION['wc_order_id']   = $data['order_id']   ?? '';
$_SESSION['wc_order_key']  = $data['order_key']  ?? '';
$_SESSION['wc_controlNum'] = $data['controlNumber'] ?? '';
$_SESSION['wc_amount']     = $data['amount']     ?? '';
$_SESSION['wc_currency']   = $data['currency']   ?? 484;

// Redirigir con UX friendly a tu checkout.php (sin tocarlo)
$dest = './checkout.php';
if (!empty($_SESSION['wc_amount'])) {
  $dest .= '?amount=' . rawurlencode($_SESSION['wc_amount']);
}
?><!doctype html>
<html lang="es"><head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Redirigiendo a Banorte…</title>
  <meta http-equiv="refresh" content="1;url=<?php echo htmlspecialchars($dest, ENT_QUOTES); ?>">
  <link rel="preconnect" href="https://multicobros.banorte.com">
  <link rel="stylesheet" href="./assets/redirect.css">
  <style>small{color:#9fb0d3}</style>
</head><body>
  <div class="wrap">
    <div class="card">
      <?php if ($logo): ?><img src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($brand, ENT_QUOTES); ?>" style="height:40px;margin-bottom:12px"/><?php endif; ?>
      <div class="spinner"></div>
      <h1>Estamos enviándote a Banorte…</h1>
      <p>No cierres esta ventana. Estás en <strong><?php echo htmlspecialchars($brand, ENT_QUOTES); ?></strong> y tu pago se procesará de forma segura.</p>
      <p><a class="btn" href="<?php echo htmlspecialchars($dest, ENT_QUOTES); ?>">Ir a Banorte ahora</a></p>
      <p><small>Si tienes problemas con la redirección automática, usa el botón.</small></p>
    </div>
  </div>
  <script>setTimeout(function(){ location.href=<?php echo json_encode($dest); ?> }, 800);</script>
</body></html>
