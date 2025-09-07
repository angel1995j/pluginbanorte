<?php
/**
 * Interstitial template for Banorte VCE
 */
if (!defined('ABSPATH')) { exit; }
/**
 * Vars:
 * $endpointJs, $order, $order_id, $order_key, $payload, $ajax_url, $return_ok, $return_cancel, $start_cfg
 */
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="robots" content="noindex,nofollow" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Conectando con Banorte…</title>
  <?php if (function_exists('wp_head')) wp_head(); ?>
  <script id="banorte-checkoutv2-js" src="<?php echo esc_url($endpointJs); ?>" crossorigin="anonymous"></script>
  <script>
    window.BANORTE_VCE_STARTCFG = <?php echo wp_json_encode(isset($start_cfg) ? $start_cfg : array()); ?>;
  </script>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}
    .card{background:#111827;border:1px solid #1f2937;border-radius:16px;max-width:640px;width:100%;padding:28px;box-shadow:0 8px 24px rgba(0,0,0,.35)}
    h2{margin:0 0 8px;font-size:22px}
    .muted{color:#9ca3af;margin:0 0 16px}
    .row{display:flex;gap:16px;margin-top:16px;flex-wrap:wrap}
    .btn{appearance:none;border:0;border-radius:12px;padding:10px 14px;background:#374151;color:#e5e7eb;cursor:pointer}
    .btn:hover{background:#4b5563}
  </style>
</head>
<body>
  <div class="card">
    <h2>Conectando con Banorte…</h2>
    <p class="muted">No cierres esta ventana.</p>
    <div id="banorte-vce-bootstrap"
         data-ajax="<?php echo esc_attr($ajax_url); ?>"
         data-done="<?php echo esc_attr($return_ok); ?>"
         data-cancel="<?php echo esc_attr($return_cancel); ?>"
         data-lb="<?php echo esc_attr($endpointJs); ?>" data-mode="<?php echo esc_attr(isset($start_cfg['mode']) ? $start_cfg['mode'] : 'PRD'); ?>"></div>
    <div class="row">
      <button class="btn" onclick="location.href='<?php echo esc_url($return_cancel); ?>'">Cancelar</button>
    </div>
  </div>
  <?php if (function_exists('wp_footer')) wp_footer(); ?>
</body>
</html>
