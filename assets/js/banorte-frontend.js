(function($){
  function log(){ try{ console.log.apply(console, ['[VCE]'].concat([].slice.call(arguments))); }catch(e){} }

  // Inyecta script si no está o si fue removido, y espera a Payment.startPayment
  function ensurePaymentReady(timeoutMs){
    timeoutMs = timeoutMs || 12000;
    return new Promise(function(resolve, reject){
      var start = Date.now();
      var injected = false;

      function ready(){
        return (window.Payment && typeof window.Payment.startPayment === 'function');
      }

      function inject(){
        var existing = document.getElementById('banorte-lightbox');
        var src = (existing && existing.getAttribute('src')) || (window.BANORTE_VCE ? window.BANORTE_VCE.lightboxSrc : null);
        if (!src) return;
        if (!document.getElementById('banorte-lightbox-fallback')) {
          var s = document.createElement('script');
          s.id = 'banorte-lightbox-fallback';
          s.src = src;
          s.setAttribute('data-cfasync','false');
          s.setAttribute('data-no-minify','1');
          s.setAttribute('data-no-defer','1');
          s.async = false;
          s.defer = false;
          (document.head || document.documentElement).appendChild(s);
          log('Fallback inyectado:', src);
          injected = true;
        }
      }

      (function tick(){
        if (ready()){
          try {
            if (typeof window.Payment.setEnv === 'function') {
              window.Payment.setEnv('pro'); // Banorte: siempre 'pro'
              log('Payment.setEnv: pro');
            }
          } catch(e){ log('setEnv error', e); }
          return resolve(true);
        }
        if (!injected) inject();
        if (Date.now() - start > timeoutMs) {
          return reject(new Error('Lightbox no disponible'));
        }
        setTimeout(tick, 150);
      })();
    });
  }

  // Fallback: crear modal simple e inyectar iframe + POST del token a /orquestador/V2
  function openFallbackV2(token){
    var modal = document.getElementById('banorte-vce-modal');
    if (!modal){
      modal = document.createElement('div');
      modal.id = 'banorte-vce-modal';
      modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999999;display:flex;align-items:center;justify-content:center;padding:2%';
      modal.innerHTML =
        '<div style="background:#fff;width:100%;max-width:720px;min-height:520px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.3);position:relative;">' +
          '<button id="banorte-vce-close" style="position:absolute;right:10px;top:10px;border:0;background:#eee;border-radius:8px;padding:6px 10px;cursor:pointer">Cerrar</button>' +
          '<iframe id="banorte-vce-frame" name="banorte-vce-frame" style="width:100%;height:100%;border:0;border-radius:12px;"></iframe>' +
        '</div>';
      document.body.appendChild(modal);
      modal.querySelector('#banorte-vce-close').onclick = function(){ modal.remove(); };
    }
    var iframe = document.getElementById('banorte-vce-frame');

    // POST (token) -> /orquestador/V2 dentro del iframe
    var form = document.createElement('form');
    form.style.display = 'none';
    form.target = 'banorte-vce-frame';
    form.method = 'POST';
    form.action = 'https://multicobros.banorte.com/orquestador/V2';

    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'token';
    input.value = token;
    form.appendChild(input);

    document.body.appendChild(form);
    form.submit();
    form.remove();

    log('Fallback modal abierto.');
  }

  // Exponemos una función global que TU flujo llamará cuando ya tengas Params
  // (cifrado vía wsCifrado + authenticateV2 al token)
  window.BanorteStartPayment = async function(paramsString, opts){
    opts = opts || {};
    log('Llamando BanorteStartPayment...');
    try {
      await ensurePaymentReady(15000);
      log('Payment listo, abriendo lightbox...');
      window.Payment.startPayment({
        Params: paramsString,
        onSuccess: opts.onSuccess || function(resp){
          log('VCE onSuccess:', resp);
          var nc = (resp && (resp.numeroControl || resp.controlNumber)) || '';
          window.location = (opts.successUrl || (window.location.origin + '/?banorte=ok')) + '&control=' + encodeURIComponent(nc);
        },
        onError: opts.onError || function(resp){
          log('VCE onError:', resp);
          alert('Error en el pago.\n' + JSON.stringify(resp, null, 2));
          window.location = (opts.errorUrl || (window.location.origin + '/?banorte=err'));
        },
        onCancel: opts.onCancel || function(){
          log('VCE onCancel');
          window.location = (opts.cancelUrl || (window.location.origin + '/?banorte=cancel'));
        },
        onClosed: function(){ log('VCE onClosed'); }
      });
    } catch(e){
      // Si el lightbox sigue sin aparecer, como último recurso prueba el fallback por token
      log('Lightbox no disponible, usando fallback. Motivo:', e && e.message);
      if (opts && opts.token) {
        openFallbackV2(opts.token);
      } else {
        alert('No se pudo iniciar el pago: Lightbox no disponible.');
      }
    }
  };

  // Diagnóstico inicial
  window.addEventListener('load', function(){
    log('Payment:', typeof window.Payment, 'startPayment:', (window.Payment ? typeof window.Payment.startPayment : 'n/a'));
  });

})(jQuery);
