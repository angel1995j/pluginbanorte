(function(){
  'use strict';

  var MAX_WAIT_MS = 60000;

  function log(){ try{ console.log.apply(console, ['[VCE]'].concat([].slice.call(arguments))); }catch(e){} }
  function err(){ try{ console.error.apply(console, ['[VCE]'].concat([].slice.call(arguments))); }catch(e){} }

  function hasPayment(){ return !!window.Payment; }
  function hasAuth(){ return !!(window.Payment && typeof window.Payment.authenticateV2 === 'function'); }
  function hasStart(){ return !!(window.Payment && (typeof window.Payment.startPaymentV2 === 'function' || typeof window.Payment.startPayment === 'function' || typeof window.Payment.configure === 'function')); }

  function waitUntil(checkFn, timeoutMs, label){
    var started = Date.now();
    return new Promise(function(resolve, reject){
      (function poll(){
        if (checkFn()) return resolve(label || 'ok');
        if (Date.now() - started > timeoutMs) return reject(new Error('Timeout waiting for ' + (label || 'condition')));
        setTimeout(poll, 120);
      })();
    });
  }

  function callEncrypt(ajaxUrl){
    if (!ajaxUrl) return Promise.reject(new Error('Missing AJAX URL'));
    log('Encrypt ->', ajaxUrl);
    return fetch(ajaxUrl, { credentials: 'same-origin' })
      .then(function(r){
        return r.text().then(function(t){
          var status = r.status;
          var data;
          try { data = JSON.parse(t); } catch(e) { data = null; }
          if (!r.ok) {
            var msg = (data && data.data && (data.data.msg || data.data.error)) || (t && t.trim()) || ('HTTP ' + status);
            if (msg === '0') { msg = 'Ajax action no registrada o plugin no cargado'; }
            if (data && data.data && typeof data.data.code !== 'undefined') { msg += ' (code ' + data.data.code + ')'; }
            if (data && data.data && data.data.err) { msg += ' - ' + data.data.err; }
            throw new Error(msg);
          }
          return data;
        });
      })
      .then(function(data){
        if (!data || data.success !== true) {
          var m = (data && data.data && (data.data.msg || data.data.error)) || 'Unknown error';
          throw new Error(m);
        }
        log('Encrypt OK');
        return data.data; // {mode,sub1,sub2} or {token,post_url}
      });
  }

  function startSession(cfg){
    cfg = cfg || {};
    return new Promise(function(resolve){
      function tryStart(){
        try {
          if (window.Payment && typeof window.Payment.startPaymentV2 === 'function') {
            log('Calling Payment.startPaymentV2', {mode: cfg.mode});
            var r = window.Payment.startPaymentV2(cfg);
            if (r && typeof r.then === 'function') return r.then(function(){ resolve('ok'); }).catch(function(){ resolve('ok'); });
            return resolve('ok');
          }
          if (window.Payment && typeof window.Payment.startPayment === 'function') {
            log('Calling Payment.startPayment (object|args)', {mode: cfg.mode});
            try {
              var r1 = window.Payment.startPayment(cfg);
              if (r1 && typeof r1.then === 'function') return r1.then(function(){ resolve('ok'); }).catch(function(){ resolve('ok'); });
              return resolve('ok');
            } catch(e1){
              try {
                var r2 = window.Payment.startPayment(cfg.user, cfg.password, cfg.affiliateId, cfg.terminalId, cfg.merchantName, cfg.merchantCity, cfg.lang, cfg.mode);
                if (r2 && typeof r2.then === 'function') return r2.then(function(){ resolve('ok'); }).catch(function(){ resolve('ok'); });
                return resolve('ok');
              } catch(e2){
                // fallthrough to configure
              }
            }
          }
          if (window.Payment && typeof window.Payment.configure === 'function') {
            log('Calling Payment.configure', {mode: cfg.mode});
            try { window.Payment.configure(cfg); } catch(e3){}
            return resolve('ok');
          }
        } catch(e){}
        // If we reached here, not ready yet
        setTimeout(tryStart, 150);
      }
      tryStart();
    });
  }

  function startLightbox(resp, okUrl, cancelUrl){
    if (!resp) throw new Error('Empty response');
    // token/post fallback
    if (resp.mode === 'token_post' && resp.post_url && resp.token) {
      log('Mode token_post -> submit form');
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = resp.post_url;
      form.style.display = 'none';
      var input = document.createElement('input');
      input.type = 'hidden'; input.name = 'token'; input.value = resp.token;
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
      return;
    }
    // sub1/sub2
    if (resp.mode === 'sub1sub2') {
      var sub1 = resp.sub1 || ''; var sub2 = resp.sub2 || '';
      if (!sub1 || !sub2) throw new Error('Missing sub1/sub2');
      log('Launching authenticateV2');
      try {
        window.Payment.authenticateV2({
          llave: sub1,
          data: sub2,
          onResult: function(result){
            log('VCE result:', result);
            try { sessionStorage.setItem('banorte_vce_result', JSON.stringify(result)); } catch(e){}
            if (result && result.status === 'A') {
              window.location.href = okUrl;
            } else {
              window.location.href = cancelUrl;
            }
          }
        });
      } catch(e){
        err('authenticateV2 failed', e);
        alert('No se pudo iniciar el pago. Intenta de nuevo.');
        window.location.href = cancelUrl;
      }
      return;
    }
    throw new Error('Unexpected mode: ' + resp.mode);
  }

  document.addEventListener('DOMContentLoaded', function(){
    var node = document.getElementById('banorte-vce-bootstrap');
    if (!node) return;

    var ajaxUrl   = node.getAttribute('data-ajax')   || '';
    var okUrl     = node.getAttribute('data-done')   || '/';
    var cancelUrl = node.getAttribute('data-cancel') || '/';
    var lightboxSrc = node.getAttribute('data-lb')   || (window.endpointJs || '');
    var startCfg  = window.BANORTE_VCE_STARTCFG || {};
    if (!startCfg.mode) { startCfg.mode = (node.getAttribute('data-mode') || 'PRD'); }

    log('checkoutV2.js tags (initial):', document.querySelectorAll("script[src*='checkoutV2.js']").length, 'mode:', startCfg.mode);

    // Kick off:
    // 1) Wait until Payment object exists (not authenticateV2), then start session ASAP.
    // 2) In parallel, call encrypt.
    // 3) After session started, wait specifically for authenticateV2.
    var pPaymentObj = waitUntil(hasPayment, MAX_WAIT_MS, 'Payment object');
    var pSession    = pPaymentObj.then(function(){ return startSession(startCfg); });
    var pEncrypt    = callEncrypt(ajaxUrl);

    pSession
      .then(function(){ log('Session ready'); return waitUntil(hasAuth, MAX_WAIT_MS, 'Payment.authenticateV2'); })
      .then(function(){ return pEncrypt; })
      .then(function(resp){ startLightbox(resp, okUrl, cancelUrl); })
      .catch(function(e){
        err('Bootstrap VCE error:', e && e.message ? e.message : e);
        alert('No se pudo cargar el sistema de pago.');
        window.location.href = cancelUrl;
      });
  });
})();