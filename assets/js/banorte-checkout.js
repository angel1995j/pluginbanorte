(function($) {
    'use strict';
    
    var BanorteCheckout = {
        init: function() {
            console.log('Banorte Checkout integration loaded');
            
            // Obtener el order_id de diferentes maneras
            this.getOrderId();
            
            // Manejar la selección del método de pago
            this.handlePaymentMethodSelection();
            
            // Manejar el envío del formulario
            this.handleFormSubmission();
        },
        
        getOrderId: function() {
            var orderId = null;
            
            // Intentar obtener el order_id de diferentes formas
            try {
                // 1. Desde la variable localizada
                if (typeof banorte_ajax !== 'undefined' && banorte_ajax.order_id) {
                    orderId = banorte_ajax.order_id;
                    console.log('Order ID from localized variable:', orderId);
                }
                
                // 2. Desde la URL (para order-pay)
                if (!orderId && window.location.href.includes('order-pay')) {
                    var urlParams = new URLSearchParams(window.location.search);
                    orderId = urlParams.get('order_id') || urlParams.get('order-pay');
                    console.log('Order ID from URL:', orderId);
                }
                
                // 3. Desde el formulario de checkout
                if (!orderId && $('input[name="order_id"]').length) {
                    orderId = $('input[name="order_id"]').val();
                    console.log('Order ID from form:', orderId);
                }
                
                // 4. Desde el formulario de pago
                if (!orderId && $('input[name="banorte_order_id"]').length) {
                    orderId = $('input[name="banorte_order_id"]').val();
                    console.log('Order ID from banorte form:', orderId);
                }
                
                if (orderId) {
                    this.orderId = orderId;
                    console.log('Final Order ID:', this.orderId);
                } else {
                    console.warn('No order ID found');
                }
                
            } catch (error) {
                console.error('Error getting order ID:', error);
            }
        },
        
        handlePaymentMethodSelection: function() {
            $(document).on('change', 'input[name="payment_method"]', function() {
                if ($(this).val() === 'banorte') {
                    console.log('Banorte payment method selected');
                }
            });
        },
        
        handleFormSubmission: function() {
            var self = this;
            
            $(document).on('click', '#place_order', function(e) {
                var paymentMethod = $('input[name="payment_method"]:checked').val();
                
                if (paymentMethod === 'banorte') {
                    e.preventDefault();
                    console.log('Initiating Banorte payment process...');
                    
                    // Si no tenemos orderId, intentar obtenerlo nuevamente
                    if (!self.orderId) {
                        self.getOrderId();
                    }
                    
                    // Redirigir al checkout de Banorte
                    self.redirectToBanorteCheckout();
                }
            });
        },
        
        redirectToBanorteCheckout: function() {
            if (!this.orderId) {
                console.error('No order ID found after multiple attempts');
                alert('Error: No se pudo obtener el número de orden. Por favor complete la información de envío primero.');
                return;
            }
            
            // Construir URL de checkout
            var checkoutUrl = banorte_ajax.checkout_url + '?order_id=' + this.orderId;
            
            console.log('Redirecting to Banorte checkout:', checkoutUrl);
            window.location.href = checkoutUrl;
        }
    };
    
    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        BanorteCheckout.init();
    });
    
})(jQuery);