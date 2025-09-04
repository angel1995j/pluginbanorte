<?php
/**
 * @var string $endpointJs
 * @var WC_Order $order
 * @var int $order_id
 * @var string $order_key
 * @var array $payload   (datos que se cifrarán en servidor)
 * @var string $ajax_url
 * @var string $return_ok
 * @var string $return_cancel
 * @var string $return_retry
 */
if (!defined('ABSPATH')) { exit; }
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Procesando pago…</title>

<?php wp_print_scripts('jquery'); ?>

<?php if (!empty($endpointJs)): ?>
<script src="<?php echo esc_url($endpointJs); ?>"></script>
<?php else: ?>
<script>console.error('checkoutV2.js no definido');</script>
<?php endif; ?>

<style>
  :root { color-scheme: light dark; }
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 20px; }
  .card { max-width: 880px; margin: 0 auto; border:1px solid #eee; border-radius:12px; padding:16px; background:#fafafa; }
  pre { background:#f6f8fa; padding:10px; border-radius:8px; white-space:pre-wrap; word-break:break-all; }
  .muted { color:#666; font-size:14px; }
  .btn { padding:.7rem 1rem; border:1px solid #ddd; border-radius:8px; cursor:pointer; background:#fff; }
  .btn:hover { background:#f7f7f7; }
</style>

<script>
(function(){
  // Siempre 'pro' como indicó Banorte
  document.addEventListener('DOMContentLoaded', function(){
    if (window.Payment && typeof Payment.setEnv === 'function') {
      try { Payment.setEnv('pro'); console.log('[VCE] Payment.setEnv: pro'); } catch(e){}
    }
  });

  function dbg(msg, obj){
    var el = document.getElementById('dbg');
    var t  = new Date().toISOString().replace('T',' ').replace('Z','');
    try {
      el.textContent += '['+t+'] '+msg+'\n' + (obj ? JSON.stringify(obj,null,2) : '') + "\n";
    } catch(e) {
      el.textContent += '['+t+'] '+msg+'\n' + String(obj) + "\n";
    }
  }

  async function solicitarCifrado(){
    const body = new URLSearchParams();
    body.set('action','banorte_vce_cifrar');
    body.set('order_id','<?php echo esc_js($order_id); ?>');
    body.set('order_key','<?php echo esc_js($order_key); ?>');

    dbg('POST cifrado (AJAX)', {order_id: <?php echo (int)$order_id; ?>});
    const r = await fetch('<?php echo esc_url($ajax_url); ?>', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    });
    const text = await r.text();
    dbg('Respuesta cifrado', {status:r.status, text});
    if (r.status !== 200) throw new Error('Cifrado no disponible');
    let j; try { j = JSON.parse(text); } catch(_){ throw new Error('JSON cifrado inválido'); }
    if (!j || !j.data || !j.data[0]) throw new Error('Cifrado vacío');
    return j.data[0]; // "sub1:::sub2"
  }

  async function notificar(status, code, message, control){
    const body = new URLSearchParams();
    body.set('action','banorte_vce_capture');
    body.set('order_id','<?php echo esc_js($order_id); ?>');
    body.set('order_key','<?php echo esc_js($order_key); ?>');
    body.set('status', status || '');
    if (code)    body.set('code', code);
    if (message) body.set('message', message);
    if (control) body.set('control', control);

    try {
      const r = await fetch('<?php echo esc_url($ajax_url); ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      });
      dbg('Notificación resultado', {status:r.status});
    } catch(e) { dbg('Error notificando', {message:e.message}); }
  }

  async function iniciar(){
    try {
      const paramsString = await solicitarCifrado();

      if (!window.Payment || typeof Payment.startPayment !== 'function') {
        throw new Error('Lightbox no disponible');
      }

      console.log('[VCE] Llamando Payment.startPayment...');
      Payment.startPayment({
        Params: paramsString,
        onSuccess: function(resp){
          dbg('VCE onSuccess', resp);
          var nc = (resp && (resp.numeroControl || resp.controlNumber)) || '';
          notificar('A', '', '', nc).then(function(){
            window.location = '<?php echo esc_url_raw($return_ok); ?>';
          });
        },
        onError: function(resp){
          dbg('VCE onError', resp);
          var code = (resp && (resp.id || resp.code)) || '';
          var msg  = (resp && (resp.message || resp.descripcion || resp.error)) || 'Error';
          notificar('E', code, msg).then(function(){
            window.location = '<?php echo esc_url_raw($return_retry); ?>';
          });
        },
        onCancel: function(){
          dbg('VCE onCancel');
          notificar('C', '', 'Cancelado').then(function(){
            window.location = '<?php echo esc_url_raw($return_cancel); ?>';
          });
        },
        onClosed: function(){ dbg('VCE onClosed'); }
      });

    } catch(e) {
      dbg('Excepción', {message:e.message, stack:e.stack});
      alert('No se pudo iniciar el pago: '+e.message);
      window.location = '<?php echo esc_url_raw($return_retry); ?>';
    }
  }

  window.addEventListener('load', iniciar);
})();
</script>
</head>
<body>
<div class="card">
  <h2>Conectando con Banorte…</h2>
  <p class="muted">No cierres esta ventana.</p>
  <pre id="dbg"></pre>
  <p><button class="btn" onclick="location.href='<?php echo esc_url($return_cancel); ?>'">Cancelar</button></p>
</div>
</body>
</html>
