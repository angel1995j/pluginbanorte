jQuery(document).ready(function($) {
    'use strict';

    function initBanorteVCE() {
        console.log('Inicializando Banorte VCE...');
        
        // Verificar que Payment esté disponible
        if (typeof Payment === 'undefined') {
            console.error('Payment library not loaded');
            $('#banorte-vce-messages').html('<p class="error">Error: No se pudo cargar el sistema de pago. Recarga la página.</p>');
            return;
        }

        // Configurar ambiente
        try {
            Payment.setEnv('pro');
            console.log('Banorte environment set to production');
        } catch (e) {
            console.warn('Could not set Banorte environment:', e);
        }

        // Obtener datos de la orden
        var orderData = window.banorte_order_data;
        if (!orderData) {
            console.error('Order data not found');
            return;
        }

        // Preparar datos para cifrado
        var orderParams = {
            merchantId: banorte_vce_params.merchant_id,
            name: banorte_vce_params.user,
            password: banorte_vce_params.password,
            mode: banorte_vce_params.mode,
            controlNumber: 'WC_' + orderData.order_id + '_' + Date.now(),
            terminalId: banorte_vce_params.terminal_id,
            amount: orderData.amount.toFixed(2),
            merchantName: banorte_vce_params.merchant_name,
            merchantCity: banorte_vce_params.merchant_city,
            lang: 'ES'
        };

        console.log('Sending order data:', orderParams);

        // Llamar al endpoint de cifrado
        $.ajax({
            url: banorte_vce_params.ajax_url,
            type: 'POST',
            data: {
                action: 'banorte_encrypt_data',
                nonce: banorte_vce_params.nonce,
                order_data: orderParams
            },
            success: function(response) {
                if (response.success && response.data.params) {
                    startPayment(response.data.params);
                } else {
                    console.error('Encryption error:', response);
                    $('#banorte-vce-messages').html('<p class="error">Error al procesar el pago. Intenta nuevamente.</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                $('#banorte-vce-messages').html('<p class="error">Error de conexión. Recarga la página.</p>');
            }
        });
    }

    function startPayment(paramsString) {
        console.log('Starting payment with params:', paramsString);
        
        try {
            Payment.startPayment({
                Params: paramsString,
                onSuccess: function(response) {
                    console.log('Payment success:', response);
                    var controlNumber = response.numeroControl || response.controlNumber || '';
                    window.location.href = banorte_vce_params.ajax_url + 
                        '?action=banorte_vce_response&status=A&order_id=' + 
                        window.banorte_order_data.order_id + '&control=' + controlNumber;
                },
                onError: function(response) {
                    console.error('Payment error:', response);
                    $('#banorte-vce-messages').html('<p class="error">Error en el pago: ' + 
                        (response.message || 'Error desconocido') + '</p>');
                    window.location.href = banorte_vce_params.ajax_url + 
                        '?action=banorte_vce_response&status=E&order_id=' + 
                        window.banorte_order_data.order_id;
                },
                onCancel: function() {
                    console.log('Payment cancelled');
                    window.location.href = banorte_vce_params.ajax_url + 
                        '?action=banorte_vce_response&status=C&order_id=' + 
                        window.banorte_order_data.order_id;
                },
                onClosed: function() {
                    console.log('Payment modal closed');
                }
            });
        } catch (e) {
            console.error('Payment initialization error:', e);
            $('#banorte-vce-messages').html('<p class="error">Error al iniciar el pago: ' + e.message + '</p>');
        }
    }

    // Inicializar cuando el DOM esté listo
    if ($('#banorte-vce-container').length) {
        setTimeout(initBanorteVCE, 1000); // Dar tiempo a que carguen los scripts
    }
});