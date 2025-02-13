<?php

//load classes init method
add_action('init', array('PMProGateway_CCBill', 'init'));
add_filter('pmpro_is_ready', array( 'PMProGateway_CCBill', 'pmpro_is_ccbill_ready' ), 999, 1 );

class PMProGateway_CCBill extends PMProGateway {

	function __construct( $gateway = NULL ) {

		$this->gateway = $gateway;
		return $this->gateway;
	}

	/**
	 * Run on WP init
	 *
	 * @since 1.8
	 */
	static function init() {

		//make sure CCBill is a gateway option
		add_filter( 'pmpro_gateways', array( 'PMProGateway_CCBill', 'pmpro_gateways' ));
		add_filter( 'pmpro_gateways_with_pending_status', array( 'PMProGateway_CCBill', 'pmpro_gateways_with_pending_status' ) );

		//add fields to payment settings
		add_filter( 'pmpro_payment_options', array( 'PMProGateway_CCBill', 'pmpro_payment_options' ));
		add_filter( 'pmpro_payment_option_fields', array( 'PMProGateway_CCBill', 'pmpro_payment_option_fields' ), 10, 2);
		//code to add at checkout
		$gateway = pmpro_getGateway();

		if ( $gateway == "ccbill" ) {

			add_filter( 'pmpro_include_payment_information_fields', '__return_false');
			add_filter( 'pmpro_required_billing_fields', array( 'PMProGateway_CCBill', 'pmpro_required_billing_fields' ) );
			add_filter( 'pmpro_checkout_default_submit_button', array( 'PMProGateway_CCBill', 'pmpro_checkout_default_submit_button' ) );
		}
	}

	static function pmpro_gateways_with_pending_status( $gateways ) {

		$gateways[] = 'ccbill';
		return $gateways;

	}


	/**
	 * Make sure this gateway is in the gateways list
	 *
	 * @since 1.8
	 */
	static function pmpro_gateways( $gateways ) {

		if ( empty( $gateways['ccbill'] ) ) {
			$gateways['ccbill'] = __( 'CCBill', 'pmpro-ccbill' );
		}

		return $gateways;
	}

	/**
	 * Get a list of payment options that the this gateway needs/supports.
	 *
	 * @since 1.8
	 */
	static function getGatewayOptions() {

		$options = array(
			'sslseal',
			'nuclear_HTTPS',
			'gateway_environment',
			'ccbill_account_number',
			'ccbill_subaccount_number',
			'ccbill_datalink_username',
			'ccbill_datalink_password',
			'ccbill_flex_form_id',
			'ccbill_salt',
			'currency',
			'use_ssl',
			'tax_state',
			'tax_rate'
		);

		return $options;
	}

	/**
	 * Set payment options for payment settings page.
	 *
	 * @since 1.8
	 */
	static function pmpro_payment_options( $options ) {
		//get ccbill options
		$ccbill_options = PMProGateway_CCBill::getGatewayOptions();

		//merge with others.
		$options = array_merge( $ccbill_options, $options );

		return $options;
	}

	/**
	 * Check if all fields are complete
	 */
	static function pmpro_is_ccbill_ready( $ready ){

		if ( get_option('pmpro_ccbill_account_number') == "" ||
		get_option('pmpro_ccbill_subaccount_number') == "" ||
		get_option('pmpro_ccbill_flex_form_id') == "" ||
		get_option('pmpro_ccbill_salt') == "" ||
		get_option('pmpro_ccbill_datalink_username') == "" ||
		get_option('pmpro_ccbill_datalink_password') == "" ){
			$ready = false;
		} else {
			$ready = true;
		}

		return $ready;

	}

	/**
	 * Display fields for this gateway's options.
	 *
	 * @since 1.8
	 */
	static function pmpro_payment_option_fields( $values, $gateway ) {
	?>
	<tr class="pmpro_settings_divider gateway gateway_ccbill" <?php if( $gateway != "ccbill" ) { ?>style="display: none;"<?php } ?> >
		<td colspan="2">
			<h2><?php esc_html_e('CCBill Settings', 'pmpro-ccbill' ); ?></h2>
			<div class="notice notice-large notice-warning inline">
					<p class="pmpro_ccbill_notice">
						<strong><?php esc_html_e( 'Paid Memberships Pro: CCBill is currently in Beta.', 'pmpro-ccbill' ); ?></strong><br />								
						<a href="https://www.paidmembershipspro.com/add-ons/ccbill/" target="_blank"><?php esc_html_e( 'Read the documentation on getting started with Paid Memberships Pro CCBill &raquo;', 'pmpro-ccbill' ); ?></a>
					</p>
				</div>
		</td>
	</tr>


	<tr class="gateway gateway_ccbill" <?php if ( $gateway != "ccbill" ) { ?>style="display: none;"<?php } ?> >
		<th scope="row" valign="top">
			<label for="ccbill_account_number"><?php esc_html_e( 'Client Account Number', 'pmpro-ccbill' ); ?>:</label>
		</th>
		<td>
			<input type="text" id="ccbill_account_number" name="ccbill_account_number" size="60" value="<?php echo esc_attr( $values['ccbill_account_number'] ); ?>" />
			<br /><small><?php esc_html_e( 'Enter the client account number from CCBill', 'pmpro-ccbill' ); ?></small>
		</td>
	</tr>


	<tr class="gateway gateway_ccbill" <?php if ( $gateway != "ccbill" ) { ?>style="display: none;"<?php } ?> >
		<th scope="row" valign="top">
			<label for="ccbill_subaccount_number"><?php esc_html_e( 'Client SubAccount Number', 'pmpro-ccbill' ); ?>:</label>
		</th>
		<td>
			<input type="text" id="ccbill_subaccount_number" name="ccbill_subaccount_number" size="60" value="<?php echo esc_attr( $values['ccbill_subaccount_number'] ); ?>" />
			<br /><small><?php esc_html_e( 'SubAccount Number You will be using', 'pmpro-ccbill' );?></small>
		</td>
	</tr>

	<tr class="gateway gateway_ccbill" <?php if ( $gateway != "ccbill" ) { ?>style="display: none;"<?php } ?> >
		<th scope="row" valign="top">
			<label for="ccbill_datalink_username"><?php esc_html_e( 'Datalink Username', 'pmpro-ccbill' ); ?>:</label>
		</th>
		<td>
			<input type="text" id="ccbill_datalink_username" name="ccbill_datalink_username" size="60" value="<?php echo esc_attr( $values['ccbill_datalink_username'] ); ?>" />
			<br /><small><?php esc_html_e( 'Datalink username. This is different than your login username. Contact CCBill for more information.', 'pmpro-ccbill'); ?></small>
		</td>
	</tr>

	<tr class="gateway gateway_ccbill" <?php if ( $gateway != "ccbill" ) { ?>style="display: none;"<?php } ?>>
		<th scope="row" valign="top">
			<label for="ccbill_datalink_password"><?php esc_html_e( 'Datalink Password', 'pmpro-ccbill' ); ?>:</label>
		</th>
		<td>
			<input type="text" id="ccbill_datalink_password" name="ccbill_datalink_password" size="60" value="<?php echo esc_attr( $values['ccbill_datalink_password'] ); ?>" />
			<br /><small><?php esc_html_e( 'Datalink pasword. This is different than your login password. Contact CCBill for more information.', 'pmpro-ccbill' ); ?></small>
		</td>
	</tr>

	<tr class="gateway gateway_ccbill" <?php if ( $gateway != "ccbill" ) { ?>style="display: none;"<?php } ?>>
		<th scope="row" valign="top">
			<label for="ccbill_flex_form_id"><?php esc_html_e( 'Flex Form ID', 'pmpro-ccbill' );?>:</label>
		</th>
		<td>
			<input type="text" name="ccbill_flex_form_id" size="60" value="<?php echo esc_attr( $values['ccbill_flex_form_id'] ); ?>" />
			<br /><small><?php esc_html_e( 'Enter the Flex Form ID from CCBill you will be using. Note you may need to have CCBill enable Dynamic Pricing', 'pmpro-ccbill' ); ?></small>
		</td>
	</tr>


	<tr class="gateway gateway_ccbill" <?php if ( $gateway != "ccbill" ) { ?>style="display: none;"<?php } ?>>
		<th scope="row" valign="top">
			<label for="ccbill_salt"><?php esc_html_e( 'Salt', 'pmpro-ccbill' ); ?>:</label>
		</th>
		<td>
			<input type="text" name="ccbill_salt" size="60" value="<?php echo esc_attr( $values['ccbill_salt'] ); ?>" />
			<br /><small><?php esc_html_e( 'Salt value must be provided by CCBill', 'pmpro-ccbill' ); ?></small>
		</td>
	</tr>
	<tr class="gateway gateway_ccbill" <?php if ( $gateway != "ccbill" ) { ?>style="display: none;"<?php } ?>>
		<th scope="row" valign="top">
			<label><?php esc_html_e( 'CCBill Webhook URL', 'pmpro-ccbill' ); ?>:</label>
		</th>
		<td>
			<p><?php esc_html_e( 'To fully integrate with CCBill, be sure to use the following for your Webhook URL', 'pmpro-ccbill' ); ?> <pre><?php echo esc_url( admin_url("admin-ajax.php") . "?action=ccbill-webhook"); ?></pre></p>

		</td>
	</tr>
	<?php
	}

	/**
	 * Remove required billing fields
	 *
	 * @since 1.8
	 */
	static function pmpro_required_billing_fields( $fields ) {

		unset($fields['CardType']);
		unset($fields['AccountNumber']);
		unset($fields['ExpirationMonth']);
		unset($fields['ExpirationYear']);
		unset($fields['CVV']);

		return $fields;
	}

	/**
	 * Swap in our submit buttons.
	 *
	 * @since 1.8
	 */
	static function pmpro_checkout_default_submit_button( $show ) {

		global $gateway, $pmpro_requirebilling;

		//show our submit buttons
		?>
		<span id="pmpro_submit_span">
			<input type="hidden" name="submit-checkout" value="1" />
			<input type="submit" id="pmpro_btn-submit" class="<?php echo esc_attr( pmpro_get_element_class(  'pmpro_btn pmpro_btn-submit-checkout'  ) ); ?>" value="<?php if( $pmpro_requirebilling ) { esc_html_e( 'Check Out with CCBill', 'pmpro-ccbill' ); } else { esc_html_e( 'Submit and Confirm', 'pmpro-ccbill' ); } ?>" /></span>
		<?php

		//don't show the default
		return false;
	}

	/**
	 * Instead of change membership levels, send users to CCBill to pay.
	 */
	static function pmpro_checkout_before_change_membership_level( $user_id, $morder ) {

		global $wpdb, $discount_code_id;

		//if no order, no need to pay
		if ( empty( $morder ) ) {
			return;
		}

		// Bail for free checkouts.
		if ( $morder->gateway != 'ccbill' ) {
			return;
		}

		$morder->user_id = $user_id;
		$morder->saveOrder();

		//Save checkout data in order meta before sending user offsite to pay.
		pmpro_save_checkout_data_to_order( $morder );

		//save discount code use
		if ( ! empty( $discount_code_id ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->pmpro_discount_codes_uses} 
						( code_id, user_id, order_id, timestamp ) 
						VALUES( %d, %d, %s, %s )",
					$discount_code_id
				),
				$morder->user_id,
				$morder->id,
				current_time( 'mysql' )
			);
		}

		do_action( "pmpro_before_send_to_ccbill", $user_id, $morder );

		$morder->Gateway->sendToCCBill( $morder );

	}

	function get_digest($initial_price, $initial_period, $currency_code = null, $recurring_price = null, $recurring_period = null, $number_of_rebills = null, $salt = null ) {

		// Defaults.
		if( empty( $currency_code ) ) {
			$currency_code = PMProGateway_CCBill::get_currency_code();
		}
		if( empty( $salt ) ) {
			$salt = get_option('pmpro_ccbill_salt');
		}
		
		$initial_price = number_format($initial_price , 2, ".","");
		
		$stringToHash = ''
				 . $initial_price
				 . $initial_period
				 .(!empty($recurring_price) ? number_format($recurring_price, 2, '.', '') : '')
				 . $recurring_period
				 . $number_of_rebills
				 . $currency_code /*978 - EUR
								036 - AUD
								124 - CAD
								826 - GBP
								392 - JPY
								840 - USD*/
				 . $salt;

		return md5($stringToHash);
	}

	/**
	 * Process checkout.
	 *
	 */
	function process( &$order ) {

		if ( empty( $order->code ) ) {
			$order->code = $order->getRandomCode();
		}
		//clean up a couple values
		$order->payment_type = "CCBill";
		$order->CardType = "";
		$order->cardtype = "";
		$order->status = "token";
		$order->saveOrder();

		self::pmpro_checkout_before_change_membership_level( $order->user_id, $order );

		return true;
	}

	static function get_currency_code( $currency_abbr = null ) {

		global $pmpro_currency;

		$currency_code = false;

		if ( empty( $currency_abbr ) ) {
			$currency_abbr = $pmpro_currency;
		}

		switch( $currency_abbr ) {

			case 'EUR':
				$currency_code = '978';
				break;

			case 'AUD':
				$currency_code = '036';
				break;

			case 'CAD':
				$currency_code = '124';
				break;

			case 'GBP':
				$currency_code = '826';
				break;

			case 'JPY':
				$currency_code = '392';
				break;

			case 'USD':
				$currency_code = '840';
				break;
		}

		return $currency_code;
	}

	function sendToCCBill( &$order ) {
		global $pmpro_currency;

		$first_name	= pmpro_getParam('bfirstname', 'REQUEST');
		$last_name	= pmpro_getParam('blastname', 'REQUEST');
		$baddress1	= pmpro_getParam('baddress1', 'REQUEST');
		$baddress2	= pmpro_getParam('baddress2',  'REQUEST');
		$bcity		= pmpro_getParam('bcity', 'REQUEST');
		$bstate		= pmpro_getParam('bstate', 'REQUEST');
		$bzipcode	= pmpro_getParam('bzipcode', 'REQUEST');
		$bcountry	= pmpro_getParam('bcountry', 'REQUEST');
		$bphone		= pmpro_getParam('bphone', 'REQUEST');
		$bemail		= pmpro_getParam('bemail', 'REQUEST');

		$currency_code = PMProGateway_CCBill::get_currency_code();

		//get the options

		$ccbill_account_number = get_option('pmpro_ccbill_account_number');
		$ccbill_subaccount_number = get_option('pmpro_ccbill_subaccount_number');
		$ccbill_flex_form_id = get_option('pmpro_ccbill_flex_form_id');
		$ccbill_salt = get_option('pmpro_ccbill_salt');

		$ccbill_flex_forms_url = 'https://api.ccbill.com/wap-frontflex/flexforms/' . $ccbill_flex_form_id;

		$ccbill_args = array();
		$ccbill_args['clientAccnum'] = $ccbill_account_number;
		$ccbill_args['clientSubacc'] = $ccbill_subaccount_number;
		$ccbill_args['currencyCode'] = $currency_code;

		//taxes on initial amount
		$initial_payment = $order->InitialPayment;
		$initial_payment_tax = $order->getTaxForPrice($initial_payment);
		$initial_payment = pmpro_round_price( (float)$initial_payment + (float)$initial_payment_tax );

		// Recurring membership
		if ( pmpro_isLevelRecurring( $order->membership_level ) ) {

			$recurring_price = number_format($order->membership_level->billing_amount, 2, ".", "");

			$recurring_period = '';

			//TODO: Add a warning to the admin page that billing periods for CCBill
			//are best set in days, so there is no confusion over off by 1 etc.

			//figure out days based on period
			if ( $order->BillingPeriod == "Day" ){
				$recurring_period = 1*$order->membership_level->cycle_number;
			} else if ( $order->BillingPeriod == "Week" ) {
				$recurring_period = 7*$order->membership_level->cycle_number;
			} else if ( $order->BillingPeriod == "Month" ) {
				$recurring_period = 30*$order->membership_level->cycle_number;
			} else if ( $order->BillingPeriod == "Year" ) {
				$recurring_period = 365*$order->membership_level->cycle_number;
			}

			$number_of_rebills = '';

			if ( ! empty( $order->membership_level->billing_limit ) ) {
				$number_of_rebills = $order->membership_level->billing_limit;
			} else {
				$number_of_rebills = 99; //means unlimited
			}

			$ccbill_args['recurringPrice'] = $recurring_price;
			$ccbill_args['recurringPeriod'] = $recurring_period;
			$ccbill_args['numRebills'] = $number_of_rebills;
			$ccbill_args['initialPrice'] = number_format($initial_payment, 2, ".", "");

			//technically, the initial period can be different than the recurring period, but keep it consistant with the other integrations.
			$ccbill_args['initialPeriod'] = $this->get_initialPeriod( $order );
			$ccbill_args['formDigest'] = $this->get_digest($initial_payment, $ccbill_args['initialPeriod'], $currency_code, $recurring_price, $recurring_period, $number_of_rebills );
			$ccbill_args['pmpro_orderid'] = $order->id;
			$ccbill_args['email'] = $bemail;
		
		} else {	

			// Non-recurring membership
			$ccbill_args['initialPrice'] = number_format( $initial_payment, 2, ".", "" );
			$ccbill_args['initialPeriod'] = $this->get_initialPeriod( $order );
			$ccbill_args['formDigest'] = $this->get_digest( $initial_payment, $ccbill_args['initialPeriod'], $currency_code );
			$ccbill_args['pmpro_orderid'] = $order->id;
			$ccbill_args['email'] = $bemail;
		}

		//If we have these set, pass them to CCBill
		$ccbill_args['customer_fname'] = $first_name;
		$ccbill_args['customer_lname'] = $last_name;
		$ccbill_args['address1'] = $baddress1. " ".$baddress2; //only one line in CCBill for Address
		$ccbill_args['city'] = $bcity;
		$ccbill_args['state'] = $bstate;
		$ccbill_args['zipcode'] = $bzipcode;
		$ccbill_args['country'] = $bcountry;
		$ccbill_args['phone_number'] = $bphone;

		/**
		 * Filter the CCBill checkout arguments to allow more flexibility.
		 * @param array $ccbill_args array The CCBill checkout arguments generated for checkout.
		 * @since TBD
		 */
		$ccbill_args = apply_filters( 'pmpro_ccbill_checkout_args', $ccbill_args, $order );

		$ccbill_url	= add_query_arg( $ccbill_args, $ccbill_flex_forms_url );

		//redirect to CCBill
		wp_redirect( $ccbill_url );

		exit;
	}

	/**
	 * Process a cancellation in CCBill.
	 *
	 * @param MemberOrder $order The order object.
	 * @return boolean True if canceled successfuly, false otherwise.
	 * @since TBD
	 */
	function cancel( &$order ) {
		//require a payment transaction id
		if ( empty( $order->subscription_transaction_id ) ) {
			return false;
		}

		//Call the cancel subscription at gateway function
		return $this->cancel_subscription_at_gateway( $order->subscription_transaction_id );
	}

	function pmprocb_return_api_response( $code ) {

		/**
		 * Error codes and explanations obtained from CCBill documentation
		 */
		$error_codes = array(
			"0" => __( "The requested action failed.", "pmpro-ccbill" ),
			"-1" => __( "The arguments provided to authenticate the merchant were invalid or missing.", "pmpro-ccbill" ),
			"-2" => __( "The subscription id provided was invalid or the subscription type is not supported by the requested action.", "pmpro-ccbill" ),
			"-3" => __( "No record was found for the given subscription.", "pmpro-ccbill" ),
			"-4" => __( "The given subscription was not for the account the merchant was authenticated on.", "pmpro-ccbill" ),
			"-5" => __( "The arguments provided for the requested action were invalid or missing.", "pmpro-ccbill" ),
			"-6" => __( "The requested action was invalid", "pmpro-ccbill" ),
			"-7" => __( "There was an internal error or a database error and the requested action could not complete.", "pmpro-ccbill" ),
			"-8" => __( "The IP Address the merchant was attempting to authenticate on was not in the valid range.", "pmpro-ccbill" ),
			"-9" => __( "The merchant’s account has been deactivated for use on the Datalink system or the merchant is not permitted to perform the requested action", "pmpro-ccbill" ),
			"-10" => __( "The merchant has not been set up to use the Datalink system.", "pmpro-ccbill" ),
			"-11" => __( "Subscription is not eligible for a discount, recurring price less than $5.00.", "pmpro-ccbill" ),
			"-12" => __( "The merchant has unsuccessfully logged into the system 3 or more times in the last hour. The merchant should wait an hour before attempting to login again and is advised to review the login information.", "pmpro-ccbill" ),
			"-15" => __( "Merchant over refund threshold", "pmpro-ccbill" ),
			"-16" => __( "Merchant over void threshold", "pmpro-ccbill" ),
			"-23" => __( "Transaction limit reached", "pmpro-ccbill" ),
			"-24" => __( "Purchase limit reached", "pmpro-ccbill" )
		);

		if ( isset( $error_codes[$code] ) ){
			return $error_codes[$code];
		}

		return __( "Error Code Unknown", "pmpro-ccbill" );
	}

	/**
	 * Calculate the initialPeriod.
	 * @param object $order The order object.
	 * @return int The initial period.
	 */
	private function get_initialPeriod( $order ) {
		$level = $order->getMembershipLevel();
		if ( pmpro_isLevelRecurring( $level ) ) {
			// For recurring payments, period is billing period.
			$profile_start_date = pmpro_calculate_profile_start_date( $order, 'U', true );
			$period = ceil( abs( $profile_start_date - time() ) / 86400 );

			// Period cannot be > 365 days. Usually this is a leap year, but could be due to filters on the startdate.
			$period = min( $period, 365 );
			
			// NOTE: We're not supporting custom trials right now. Probably can't.
		} elseif ( $level->expiration_number > 0 ) {
			// Get the levels expiration and convert it to days.
			$expiration_date = $this->calculate_expiration_date( $level );
			$order_date = date( "Y-m-d", $order->timestamp );
			$period = round( ( strtotime( $expiration_date ) - strtotime( $order_date ) ) / DAY_IN_SECONDS ); 
		} else {
			$period = 2; //CCBill doesn't allow period values of 1.
		}

		return (int) $period;
	}

	/**
	 * Convert expiration period to date to YYYY-MM-DD from level expiration settings.
	 *
	 * @param MemberOrder $order
	 * @return string $calculated_date The calculated date of expiration date from todays date. (i.e. 2024-01-31)
	 */
	public function calculate_expiration_date( $level ) {
		//Convert $level->expiration_period + $level->expiration_number to a date.
		$expiration_date = date( "Y-m-d", strtotime( "+ " . $level->expiration_number . " " . $level->expiration_period, current_time( "timestamp" ) ) );
		return $expiration_date;
	}

	/**
	 * Cancels a subscription in CCBill.
	 *
	 * @param PMPro_Subscription $subscription to cancel.
	 * @return bool True if successful, false otherwise.
	 * @since TBD
	 */
	function cancel_subscription( $subscription ) {
		//get subscription id
		$subscription_id = $subscription->get_subscription_transaction_id();
		return $this->cancel_subscription_at_gateway( $subscription_id );
	}

	/**
	 * Cancels a subscription at the gateway.
	 *
	 * @param String $subscription_id The subscription id of the subscription to cancel.
	 * @return bool True if successful, false otherwise.
	 * @since TBD
	 */
	function cancel_subscription_at_gateway( $subscription_id ) {
		//build the URL
		$sms_link = "https://datalink.ccbill.com/utils/subscriptionManagement.cgi";

		$qargs = array();
		$qargs["action"]		= "cancelSubscription";
		$qargs["clientSubacc"]	= '';
		$qargs["usingSubacc"]	= get_option('pmpro_ccbill_subaccount_number');
		$qargs["subscriptionId"] = $subscription_id;
		$qargs["clientAccnum"]	= get_option('pmpro_ccbill_account_number');
		$qargs["username"]		= get_option('pmpro_ccbill_datalink_username'); //must be provided by CCBill
		$qargs["password"]		= get_option('pmpro_ccbill_datalink_password'); //must be provided by CCBill

		$cancel_link	= add_query_arg( $qargs, $sms_link );
		$response		= wp_remote_get( $cancel_link );

		$response_code		= wp_remote_retrieve_response_code( $response );
		$response_message	= wp_remote_retrieve_response_message( $response );

		$cancel_error = '';

		if ( 200 != $response_code && !empty( $response_message ) ) {
			//return new WP_Error( $response_code, $response_message );
			$cancel_error = sprintf( __( 'Cancellation of subscription id: %s may have failed. Check CCBill Admin to confirm cancellation', 'pmpro-ccbill'), $subscription_id );
		} else if ( 200 != $response_code ) {
			//Unknown Error Occurred
			$cancel_error = sprintf( __( 'Cancellation of subscription id: %s may have failed. Check CCBill Admin to confirm cancellation', 'pmpro-ccbill'), $subscription_id );
		} else {
			$response_body = wp_remote_retrieve_body( $response );
			$cancel_status = filter_var($response_body, FILTER_SANITIZE_NUMBER_INT);
			if ( $cancel_status < 1 ) {
				// A CCBill Error has occured. They need to contact CCBill
				$error_code = $this->pmprocb_return_api_response( $cancel_status );
				$cancel_error = sprintf( __( 'Cancellation of subscription id: %s may have failed. Check CCBill Admin to confirm cancellation. Error: %s', 'pmpro-ccbill'), $subscription_id );
			} else {
				//success, let's return true
				return true;
			}
		}

		// We want to send an email if there is a cancellation error.
		if ( ! empty( $cancel_error ) ) {
			$pmproemail = new PMProEmail();
			$body = '<p>' . $cancel_error . '</p>';
			$pmproemail->template = 'pmpro_ccbill_cancel_error';
			$pmproemail->subject = sprintf( __( 'Error cancelling subscription at %s', 'paid-memberships-pro' ), get_bloginfo( 'name' ) );
			$pmproemail->data = array( 'body' => $body );
			$pmproemail->sendEmail( get_option( 'admin_email' ) );
		}

		return false;
	}
}
