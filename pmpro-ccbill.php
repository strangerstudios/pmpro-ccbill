<?php
/*
Plugin Name: PMPro CCBill
Plugin URI: 
Description: PMPro Gateway integration for CCBill (http://www.ccbill.com)
Version: 0.1
Author: Great H Master
Author URI:
*/

define("PMPRO_CCBILL_DIR", dirname(__FILE__));
//load payment gateway class
require_once(PMPRO_CCBILL_DIR . "/classes/class.pmprogateway_ccbill.php");

function pmpro_wp_ajax_ccbill_webhook()
{
	require_once(dirname(__FILE__) . "/webhook.php");
	exit;	
}
add_action('wp_ajax_nopriv_ccbill-webhook', 'pmpro_wp_ajax_ccbill_webhook');
add_action('wp_ajax_ccbill-webhook', 'pmpro_wp_ajax_ccbill_webhook');