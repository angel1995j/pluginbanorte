<?php
/**
 * Plugin Name: Banorte VCE Woo Bridge
 * Description: Pasarela WooCommerce que redirige al checkout externo (tu /prueba/php) y recibe la notificación para cerrar el pedido. Incluye mejoras UX.
 * Version: 1.1.0
 * Author: Tu Equipo
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BANORTE_VCE_WOO_BRIDGE_VER', '1.1.0' );
define( 'BANORTE_VCE_WOO_BRIDGE_FILE', __FILE__ );
define( 'BANORTE_VCE_WOO_BRIDGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BANORTE_VCE_WOO_BRIDGE_URL', plugin_dir_url( __FILE__ ) );

// Declarar compatibilidad HPOS (High-Performance Order Storage)
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

// Dependencia básica: WooCommerce
add_action( 'plugins_loaded', function(){
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function(){
			echo '<div class="notice notice-error"><p><strong>Banorte VCE Woo Bridge</strong> requiere <em>WooCommerce</em> activo.</p></div>';
		} );
		return;
	}

	// Registrar gateway
	add_filter( 'woocommerce_payment_gateways', function( $gws ){
		require_once BANORTE_VCE_WOO_BRIDGE_DIR . 'includes/class-wc-gateway-banorte-vce-bridge.php';
		$gws[] = 'WC_Gateway_Banorte_VCE_Bridge';
		return $gws;
	} );
}, 11 );

// ==== AJAX: start (redirigir al bridge externo) ====
add_action('wp_ajax_nopriv_banorte_vce_wc_start', 'banorte_vce_wc_start');
add_action('wp_ajax_banorte_vce_wc_start',        'banorte_vce_wc_start');

function banorte_vce_wc_start() {
	if ( ! class_exists('WC_Order') ) { status_header(500); echo 'WooCommerce requerido'; exit; }

	$settings = get_option('woocommerce_banorte_vce_bridge_settings', []);
	$phpUrl   = isset($settings['php_checkout_url']) ? trim($settings['php_checkout_url']) : '';
	$secret   = isset($settings['bridge_secret'])    ? (string)$settings['bridge_secret']    : '';

	if ( empty($phpUrl) || empty($secret) ) {
		status_header(500); echo 'Bridge mal configurado (php_checkout_url / bridge_secret)'; exit;
	}

	$order_id  = absint($_REQUEST['order_id'] ?? 0);
	$order_key = sanitize_text_field($_REQUEST['order_key'] ?? '');
	if ( ! $order_id || ! $order_key ) { status_header(400); echo 'Faltan parámetros'; exit; }

	$order = wc_get_order($order_id);
	if ( ! $order || $order->get_order_key() !== $order_key ) { status_header(403); echo 'Orden no válida'; exit; }

	$amount   = number_format((float)$order->get_total(), 2, '.', '');
	$control  = $order_id . '-' . time();
	$returnWC = $order->get_checkout_order_received_url();
	$notifyUrl= admin_url('admin-ajax.php?action=banorte_vce_wc_notify');

	$payload = [
		'order_id'      => $order_id,
		'order_key'     => $order_key,
		'amount'        => $amount,
		'currency'      => 484,
		'controlNumber' => $control,
		'returnWC'      => $returnWC,
		'notifyUrl'     => $notifyUrl,
	];

	$b64 = base64_encode( wp_json_encode( $payload ) );
	$sig = hash_hmac('sha256', $b64, $secret);

	$dest = add_query_arg( [
		'bridge'  => '1',
		'payload' => rawurlencode($b64),
		'sig'     => $sig,
	], $phpUrl );

	// Intersticial amigable: pantalla de transición con spinner mientras redirige
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="1;url=' . esc_attr( $dest ) . '">';
	echo '<meta name="viewport" content="width=device-width,initial-scale=1"><title>Redirigiendo a Banorte…</title>';
	echo '<link rel="preconnect" href="https://multicobros.banorte.com">';
	echo '<style>'.file_get_contents( BANORTE_VCE_WOO_BRIDGE_DIR . 'assets/redirect.css' ).'</style>';
	echo '</head><body><div class="wrap"><div class="card"><div class="spinner"></div><h1>Estamos enviándote a Banorte…</h1><p>No cierres esta ventana. Si no avanzamos en unos segundos, usa el botón:</p><p><a class="btn" href="'.esc_url( $dest ).'">Ir a Banorte ahora</a></p></div></div><script>setTimeout(function(){location.href='.json_encode($dest).'},800);</script></body></html>';
	exit;
}

// ==== AJAX: notify (regreso desde bridge externo) ====
add_action('wp_ajax_nopriv_banorte_vce_wc_notify','banorte_vce_wc_notify');
add_action('wp_ajax_banorte_vce_wc_notify',       'banorte_vce_wc_notify');

function banorte_vce_wc_notify() {
	if ( ! class_exists('WC_Order') ) { status_header(500); echo 'WooCommerce requerido'; exit; }

	$settings = get_option('woocommerce_banorte_vce_bridge_settings', []);
	$secret   = isset($settings['bridge_secret']) ? (string)$settings['bridge_secret'] : '';
	$debug    = ! empty($settings['debug']);

	if ( empty($secret) ) { status_header(500); echo 'Bridge mal configurado (bridge_secret)'; exit; }

	$b64 = $_POST['payload'] ?? '';
	$sig = $_POST['sig']     ?? '';
	if ( empty($b64) || empty($sig) ) { status_header(400); echo 'Faltan parámetros'; exit; }

	$calc = hash_hmac('sha256', $b64, $secret);
	if ( ! hash_equals($calc, $sig) ) { status_header(403); echo 'Firma inválida'; exit; }

	$data = json_decode(base64_decode($b64), true);
	if ( ! is_array($data) ) { status_header(400); echo 'Payload inválido'; exit; }

	$order_id  = absint($data['order_id'] ?? 0);
	$order_key = sanitize_text_field($data['order_key'] ?? '');
	$status_in = (string)($data['status'] ?? 'failed');
	$status    = strtolower(trim($status_in));
	$txn_id    = sanitize_text_field($data['txn_id'] ?? '');
	$auth_code = sanitize_text_field($data['auth_code'] ?? '');
	$message   = sanitize_text_field($data['message'] ?? '');

	$order = wc_get_order($order_id);
	if ( ! $order || $order->get_order_key() !== $order_key ) { status_header(404); echo 'Orden no encontrada'; exit; }

	if ( $txn_id )   { $order->update_meta_data('_banorte_txn_id',   $txn_id); }
	if ( $auth_code ){ $order->update_meta_data('_banorte_auth_code',$auth_code); }
	if ( ! empty($data['controlNumber']) ) {
		$order->update_meta_data('_banorte_control', sanitize_text_field($data['controlNumber']));
	}
	$order->save();

	$logger = ( $debug && class_exists('WC_Logger') ) ? wc_get_logger() : null;

	$approved_aliases = ['approved','success','ok','00','a','aprobado'];
	if ( in_array( $status, $approved_aliases, true ) ) {
		$order->payment_complete( $txn_id ?: ('banorte_' . time()) );
		$order->add_order_note( 'Banorte aprobado. Auth: ' . $auth_code . ' ' . $message );
		if ( $logger ) { $logger->info( 'Banorte notify: approved (' . $status_in . ') for order ' . $order_id, ['source'=>'banorte-vce-bridge'] ); }
		wp_send_json_success( ['updated'=>'processing/completed'] );
	} elseif ( in_array( $status, ['pending','hold','on-hold'], true ) ) {
		$order->update_status('on-hold', 'Banorte en espera. ' . $message);
		if ( $logger ) { $logger->info( 'Banorte notify: on-hold for order ' . $order_id, ['source'=>'banorte-vce-bridge'] ); }
		wp_send_json_success( ['updated'=>'on-hold'] );
	} else {
		$order->update_status('failed', 'Banorte rechazado/cancelado. ' . $message . ' (status=' . $status_in . ')' );
		if ( $logger ) { $logger->warning( 'Banorte notify: failed (' . $status_in . ') for order ' . $order_id, ['source'=>'banorte-vce-bridge'] ); }
		wp_send_json_success( ['updated'=>'failed'] );
	}
}
