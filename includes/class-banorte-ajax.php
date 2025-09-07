<?php
class Banorte_VCE_AJAX {
    public function __construct() {
        add_action('wp_ajax_banorte_encrypt_data', array($this, 'encrypt_data'));
        add_action('wp_ajax_nopriv_banorte_encrypt_data', array($this, 'encrypt_data'));
        add_action('wp_ajax_banorte_vce_response', array($this, 'handle_payment_response'));
        add_action('wp_ajax_nopriv_banorte_vce_response', array($this, 'handle_payment_response'));
    }

    public function encrypt_data() {
        check_ajax_referer('banorte_vce_nonce', 'nonce');
        
        $order_data = $_POST['order_data'];
        
        // Aquí deberías implementar la llamada a tu servicio de cifrado
        // Por ahora, simulamos una respuesta exitosa
        $response = array(
            'success' => true,
            'data' => array(
                'params' => 'simulated_encrypted_params:::' . base64_encode(json_encode($order_data))
            )
        );
        
        wp_send_json($response);
    }

    public function handle_payment_response() {
        $status = $_GET['status'] ?? '';
        $order_id = $_GET['order_id'] ?? '';
        $control_number = $_GET['control'] ?? '';
        
        if ($order_id) {
            $order = wc_get_order($order_id);
            
            if ($status === 'A') {
                $order->payment_complete($control_number);
                $order->add_order_note('Pago aprobado por Banorte. Control: ' . $control_number);
                wp_redirect($order->get_checkout_order_received_url());
            } else {
                $order->update_status('failed', 'Pago rechazado por Banorte');
                wc_add_notice('Pago rechazado. Intenta con otro método.', 'error');
                wp_redirect(wc_get_checkout_url());
            }
            exit;
        }
    }
}

new Banorte_VCE_AJAX();