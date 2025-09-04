<?php
if (!defined('ABSPATH')) { exit; }

class WC_Gateway_Banorte_VCE extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'banorte_vce';
        $this->method_title       = 'Banorte VCE';
        $this->method_description = 'Cobros con Banorte Lightbox (VCE).';
        $this->has_fields         = false;
        $this->supports           = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        // Ajustes:
        $this->enabled        = $this->get_option('enabled', 'no');
        $this->title          = $this->get_option('title', 'Tarjeta (Banorte)');
        $this->description    = $this->get_option('description', 'Serás dirigido al módulo seguro de Banorte.');
        $this->mode           = strtoupper($this->get_option('mode', 'AUT')); // AUT|PRD
        $this->affiliateId    = $this->get_option('affiliateId', '');
        $this->user           = $this->get_option('user', '');
        $this->password       = $this->get_option('password', '');
        $this->terminalId     = $this->get_option('terminalId', '');
        $this->merchantName   = $this->get_option('merchantName', '');
        $this->merchantCity   = $this->get_option('merchantCity', '');
        $this->lang           = $this->get_option('lang', 'ES');

        $this->endpointJs_AUT = $this->get_option('endpointJs_AUT', 'https://multicobros.banorte.com/orquestador/static/checkoutV2.js');
        $this->endpointJs_PRD = $this->get_option('endpointJs_PRD', 'https://multicobros.banorte.com/orquestador/static/checkoutV2.js');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Activar',
                'type'    => 'checkbox',
                'label'   => 'Activar Banorte VCE',
                'default' => 'no'
            ],
            'title' => [
                'title'       => 'Título',
                'type'        => 'text',
                'description' => 'Se muestra al cliente en el checkout.',
                'default'     => 'Tarjeta (Banorte)'
            ],
            'description' => [
                'title'       => 'Descripción',
                'type'        => 'textarea',
                'default'     => 'Serás dirigido al módulo seguro de Banorte.'
            ],
            'mode' => [
                'title'       => 'Ambiente',
                'type'        => 'select',
                'description' => 'AUT para pruebas, PRD para productivo. (Payment.setEnv siempre pro, por instrucción de Banorte)',
                'options'     => ['AUT' => 'AUT (pruebas)', 'PRD' => 'PRD (producción)'],
                'default'     => 'AUT'
            ],
            'affiliateId' => [
                'title'       => 'Merchant/Affiliate ID',
                'type'        => 'text',
                'default'     => ''
            ],
            'user' => [
                'title'       => 'Usuario (name)',
                'type'        => 'text',
                'default'     => ''
            ],
            'password' => [
                'title'       => 'Password',
                'type'        => 'password',
                'default'     => ''
            ],
            'terminalId' => [
                'title'       => 'Terminal ID',
                'type'        => 'text',
                'default'     => ''
            ],
            'merchantName' => [
                'title'       => 'Nombre Comercio',
                'type'        => 'text',
                'default'     => ''
            ],
            'merchantCity' => [
                'title'       => 'Ciudad Comercio',
                'type'        => 'text',
                'default'     => ''
            ],
            'lang' => [
                'title'       => 'Idioma',
                'type'        => 'text',
                'default'     => 'ES'
            ],
            'endpointJs_AUT' => [
                'title'       => 'checkoutV2.js (AUT)',
                'type'        => 'url',
                'default'     => 'https://multicobros.banorte.com/orquestador/static/checkoutV2.js'
            ],
            'endpointJs_PRD' => [
                'title'       => 'checkoutV2.js (PRD)',
                'type'        => 'url',
                'default'     => 'https://multicobros.banorte.com/orquestador/static/checkoutV2.js'
            ],
        ];
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice('No se pudo crear el pedido.', 'error');
            return;
        }

        // Redirigimos a la "pantalla intermedia" que abre el lightbox
        $args = [
            'banorte_vce_pay' => 1,
            'order_id'        => $order_id,
            'key'             => $order->get_order_key(),
        ];
        $url = add_query_arg($args, home_url('/'));
        return [
            'result'   => 'success',
            'redirect' => $url
        ];
    }
}
