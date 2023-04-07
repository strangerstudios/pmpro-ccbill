<?php
/*
Plugin Name: Paid Memberships Pro - CCBill Gateway
Plugin URI: https://www.paidmembershipspro.com/add-ons/ccbill
Description: PMPro Gateway integration for CCBill
Version: 0.1
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
Text Domain: pmpro-ccbill
Domain Path: /languages
*/

define( "PMPRO_CCBILL_DIR", dirname( __FILE__ ) );

/**
 * Loads rest of CCBill gateway if PMPro is active.
 */
function pmpro_ccbill_load_gateway() {

	if ( class_exists( 'PMProGateway' ) ) {
		require_once( PMPRO_CCBILL_DIR . '/classes/class.pmprogateway_ccbill.php' );
		add_action( 'wp_ajax_nopriv_ccbill-webhook', 'pmpro_wp_ajax_ccbill_webhook' );
		add_action( 'wp_ajax_ccbill-webhook', 'pmpro_wp_ajax_ccbill_webhook' );
	}

}
add_action( 'plugins_loaded', 'pmpro_ccbill_load_gateway' );

/**
 * Callback for CCBill Webhook
 */
function pmpro_wp_ajax_ccbill_webhook() {

	require_once( dirname(__FILE__) . "/webhook.php" );
	exit;
}
add_action( 'wp_ajax_nopriv_ccbill-webhook', 'pmpro_wp_ajax_ccbill_webhook' );
add_action( 'wp_ajax_ccbill-webhook', 'pmpro_wp_ajax_ccbill_webhook' );

/**
 * Runs only when the plugin is activated.
 *
 * @since 0.1.0
 */
function pmpro_ccbill_admin_notice_activation_hook() {
	// Create transient data.
	set_transient( 'pmpro-ccbill-admin-notice', true, 5 );
}
register_activation_hook( __FILE__, 'pmpro_ccbill_admin_notice_activation_hook' );

/**
 * Admin Notice on Activation.
 *
 * @since 0.1.0
 */
function pmpro_ccbill_admin_notice() {
	// Check transient, if available display notice.
	if ( get_transient( 'pmpro-ccbill-admin-notice' ) ) { 
	?>
		<div class="updated notice is-dismissible">
			<p><?php printf( __( 'Thank you for activating the Paid Memberships Pro: CCBill Add On. <a href="%s">Visit the payment settings page</a> to configure the CCBill Payment Gateway.', 'pmpro-ccbill' ), esc_url( get_admin_url( null, 'admin.php?page=pmpro-paymentsettings' ) ) ); ?></p>
		</div>
		<?php
		// Delete transient, only display this notice once.
		delete_transient( 'pmpro-ccbill-admin-notice' );
	}
}
add_action( 'admin_notices', 'pmpro_ccbill_admin_notice' );

/**
 * Function to add links to the plugin action links
 *
 * @param array $links Array of links to be shown in plugin action links.
 */
function pmpro_ccbill_plugin_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		$new_links = array(
			'<a href="' . get_admin_url( null, 'admin.php?page=pmpro-paymentsettings' ) . '">' . __( 'Configure CCBill', 'pmpro-ccbill' ) . '</a>',
		);
	}
	return array_merge( $new_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pmpro_ccbill_plugin_action_links' );

/**
 * Function to add links to the plugin row meta
 *
 * @param array  $links Array of links to be shown in plugin meta.
 * @param string $file Filename of the plugin meta is being shown for.
 */
function pmpro_ccbill_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-ccbill.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/ccbill/' ) . '" title="' . esc_attr( __( 'View Documentation', 'pmpro-ccbill' ) ) . '">' . __( 'Docs', 'pmpro-ccbill' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro-ccbill' ) ) . '">' . __( 'Support', 'pmpro-ccbill' ) . '</a>',
		);
		$links = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmpro_ccbill_plugin_row_meta', 10, 2 );

/**
 * Load the languages folder for translations.
 */
function pmproccbill_load_textdomain(){
	load_plugin_textdomain( 'pmpro-ccbill' );
}
add_action( 'plugins_loaded', 'pmproccbill_load_textdomain' );
