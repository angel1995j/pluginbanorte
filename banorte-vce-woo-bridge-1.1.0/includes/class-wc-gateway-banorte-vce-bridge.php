<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WC_Gateway_Banorte_VCE_Bridge extends WC_Payment_Gateway {

	/** Props to avoid PHP 8.2 dynamic property deprecations */
	public $php_checkout_url;
	public $bridge_secret;
	public $debug;

	public function __construct() {
		$this->id                 = 'banorte_vce_bridge';
		$this->method_title       = 'Banorte VCE (Bridge)';
		$this->method_description = 'Redirige al checkout externo (tu /prueba/php) y luego notifica a Woo. UX mejorada para el cliente.';
		$this->has_fields         = false;
		$this->supports           = [ 'products' ];
		$this->icon               = BANORTE_VCE_WOO_BRIDGE_URL . 'assets/banorte.svg';

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled          = $this->get_option( 'enabled', 'yes' );
		$this->title            = $this->get_option( 'title', 'Tarjeta (Banorte)' );
		$this->description      = $this->get_option( 'description', 'Serás redirigido a la plataforma segura de Banorte para completar tu pago.' );
		$this->php_checkout_url = $this->get_option( 'php_checkout_url', '' );
		$this->bridge_secret    = $this->get_option( 'bridge_secret', '' );
		$this->debug            = $this->get_option( 'debug', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title'   => 'Activar/Desactivar',
				'type'    => 'checkbox',
				'label'   => 'Activar Banorte VCE (Bridge)',
				'default' => 'yes',
			],
			'title' => [
				'title'       => 'Título',
				'type'        => 'text',
				'description' => 'Lo verá el cliente en el checkout',
				'default'     => 'Tarjeta (Banorte)',
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => 'Descripción',
				'type'        => 'textarea',
				'default'     => 'Serás redirigido a la plataforma segura de Banorte para completar tu pago.',
			],
			'php_checkout_url' => [
				'title'       => 'URL del checkout externo (bridge.php)',
				'type'        => 'text',
				'description' => 'Ej: https://tu-dominio/prueba/php/bridge.php',
				'default'     => '',
				'desc_tip'    => true,
			],
			'bridge_secret' => [
				'title'       => 'Secreto compartido (HMAC)',
				'type'        => 'password',
				'description' => 'Cadena larga y aleatoria. Debe ser la misma en tu bridge.php/callback.php',
				'default'     => '',
			],
			'debug' => [
				'title'       => 'Registro (debug)',
				'type'        => 'checkbox',
				'label'       => 'Escribir eventos en WooCommerce > Estado > Logs (banorte-vce-bridge)',
				'default'     => 'no',
			],
		];
	}

	public function is_available() {
		if ( 'yes' !== $this->enabled ) return false;
		if ( empty( $this->php_checkout_url ) || empty( $this->bridge_secret ) ) return false;
		return true;
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return [ 'result' => 'failure' ];

		$redirect = add_query_arg( [
			'action'    => 'banorte_vce_wc_start',
			'order_id'  => $order_id,
			'order_key' => $order->get_order_key(),
		], admin_url( 'admin-ajax.php' ) );

		return [
			'result'   => 'success',
			'redirect' => $redirect,
		];
	}
}
