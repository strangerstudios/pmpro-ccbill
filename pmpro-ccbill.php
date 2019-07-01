<?php
/*
Plugin Name: PMPro CCBill
Plugin URI: 
Description: PMPro Gateway integration for CCBill (http://www.ccbill.com)
Version: 0.1
Author: greathmaster
Author URI:
*/

define("PMPRO_CCBILL_DIR", dirname(__FILE__));

/**
 * Loads rest of CCBill gateway if PMPro is active.
 */
function pmproccbill_load_gateway() {
	if ( class_exists( 'PMProGateway' ) ) {
		require_once( PMPRO_CCBILL_DIR . '/classes/class.pmprogateway_ccbill.php' );
		add_action( 'wp_ajax_nopriv_ccbill-webhook', 'pmpro_wp_ajax_ccbill_webhook' );
		add_action( 'wp_ajax_ccbill-webhook', 'pmpro_wp_ajax_ccbill_webhook' );
	}
}
add_action( 'plugins_loaded', 'pmproccbill_load_gateway' );

function pmpro_wp_ajax_ccbill_webhook()
{
	require_once(dirname(__FILE__) . "/webhook.php");
	exit;	
}