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
		
		add_filter( 'pmpro_allowed_refunds_gateways', array( 'PMProGateway_CCBill', 'allow_refunds' ), 10, 1 ); 
		add_filter( 'pmpro_process_refund_ccbill', array( 'PMProGateway_CCBill', 'process_refund' ), 10, 2 );

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

	static function allow_refunds( $gateways ) {
		$gateways[] = 'ccbill';
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
	 * Check whether or not a gateway supports a specific feature.
	 *
	 * @param string $feature The feature to check.
	 * @return bool True if the gateway supports the feature, false if not.
	 * @since TBD
	 */
	public static function supports( $feature ) {
		$supports = array(
			'subscription_sync' => true,
		);

		if ( empty( $supports[$feature] ) ) {
			return false;
		}

		return $supports[$feature];
	}

	/**
	 * Display fields for this gateway's options
	 * 
	 * @since TBD
	 */
	static function show_settings_fields() {
		?>
		<div class="notice notice-large notice-warning inline">
			<p class="pmpro_ccbill_notice">
				<strong><?php esc_html_e( 'Paid Memberships Pro: CCBill is currently in Beta.', 'pmpro-ccbill' ); ?></strong><br />								
				<a href="https://www.paidmembershipspro.com/add-ons/ccbill/" target="_blank"><?php esc_html_e( 'Read the documentation on getting started with Paid Memberships Pro CCBill &raquo;', 'pmpro-ccbill' ); ?></a>
			</p>
		</div>
		<div id="pmpro_ccbill" class="pmpro_section" data-visibility="shown" data-activated="true">
			<div class="pmpro_section_toggle">
				<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
					<?php esc_html_e( 'Settings', 'pmpro-ccbill' ); ?>
				</button>
			</div>
			<div class="pmpro_section_inside">
				<table class='form-table'>
					<tbody>
						<tr class="gateway gateway_ccbill">
							<th scope="row" valign="top">
								<label for="ccbill_account_number"><?php esc_html_e( 'Client Account Number', 'pmpro-ccbill' ); ?>:</label>
							</th>
							<td>
								<input type="text" id="ccbill_account_number" name="ccbill_account_number" size="60" value="<?php echo esc_attr( get_option( 'pmpro_ccbill_account_number' ) ); ?>" class="regular-text code" />
								<br /><small><?php esc_html_e( 'Enter the client account number from CCBill', 'pmpro-ccbill' ); ?></small>
							</td>
						</tr>
						<tr class="gateway gateway_ccbill">
							<th scope="row" valign="top">
								<label for="ccbill_subaccount_number"><?php esc_html_e( 'Client SubAccount Number', 'pmpro-ccbill' ); ?>:</label>
							</th>
							<td>
								<input type="text" id="ccbill_subaccount_number" name="ccbill_subaccount_number" size="60" value="<?php echo esc_attr( get_option( 'pmpro_ccbill_subaccount_number' ) ); ?>" />
								<br /><small><?php esc_html_e( 'SubAccount Number You will be using', 'pmpro-ccbill' );?></small>
							</td>
						</tr>
						<tr class="gateway gateway_ccbill">
							<th scope="row" valign="top">
								<label for="ccbill_datalink_username"><?php esc_html_e( 'Datalink Username', 'pmpro-ccbill' ); ?>:</label>
							</th>
							<td>
								<input type="text" id="ccbill_datalink_username" name="ccbill_datalink_username" size="60" value="<?php echo esc_attr( get_option( 'pmpro_ccbill_datalink_username' ) ); ?>" />
								<br /><small><?php esc_html_e( 'Datalink username. This is different than your login username. Contact CCBill for more information.', 'pmpro-ccbill'); ?></small>
							</td>
						</tr>
						<tr class="gateway gateway_ccbill">
							<th scope="row" valign="top">
								<label for="ccbill_datalink_password"><?php esc_html_e( 'Datalink Password', 'pmpro-ccbill' ); ?>:</label>
							</th>
							<td>
								<input type="text" id="ccbill_datalink_password" name="ccbill_datalink_password" size="60" value="<?php echo esc_attr( get_option( 'pmpro_ccbill_datalink_password' ) ); ?>" />
								<br /><small><?php esc_html_e( 'Datalink pasword. This is different than your login password. Contact CCBill for more information.', 'pmpro-ccbill' ); ?></small>
							</td>
						</tr>
						<tr class="gateway gateway_ccbill">
							<th scope="row" valign="top">
								<label for="ccbill_flex_form_id"><?php esc_html_e( 'Flex Form ID', 'pmpro-ccbill' );?>:</label>
							</th>
							<td>
								<input type="text" name="ccbill_flex_form_id" size="60" value="<?php echo esc_attr( get_option( 'pmpro_ccbill_flex_form_id' ) ); ?>" />
								<br /><small><?php esc_html_e( 'Enter the Flex Form ID from CCBill you will be using. Note you may need to have CCBill enable Dynamic Pricing', 'pmpro-ccbill' ); ?></small>
							</td>
						</tr>
						<tr class="gateway gateway_ccbill">
							<th scope="row" valign="top">
								<label for="ccbill_salt"><?php esc_html_e( 'Salt', 'pmpro-ccbill' ); ?>:</label>
							</th>
							<td>
								<input type="text" name="ccbill_salt" size="60" value="<?php echo esc_attr( get_option( 'pmpro_ccbill_salt' ) ); ?>" />
								<br /><small><?php esc_html_e( 'Salt value must be provided by CCBill', 'pmpro-ccbill' ); ?></small>
							</td>
						</tr>
						<tr class="gateway gateway_ccbill">
							<th scope="row" valign="top">
								<label><?php esc_html_e( 'CCBill Webhook URL', 'pmpro-ccbill' ); ?>:</label>
							</th>
							<td>
								<p><?php esc_html_e( 'To fully integrate with CCBill, be sure to use the following for your Webhook URL', 'pmpro-ccbill' ); ?> <pre><?php echo esc_url( admin_url("admin-ajax.php") . "?action=ccbill-webhook"); ?></pre></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Save the payment gateway settings fields for PMPro V3.5+.
	 * 
	 * @since TBD
	 */
	public static function save_settings_fields() {
		$settings_to_save = array(
			'ccbill_account_number',
			'ccbill_subaccount_number',
			'ccbill_datalink_username',
			'ccbill_datalink_password',
			'ccbill_flex_form_id',
			'ccbill_salt'
		);

		foreach ( $settings_to_save as $setting ) {
			if ( isset( $_REQUEST[ $setting ] ) ) {
				update_option( 'pmpro_' . $setting, sanitize_text_field( $_REQUEST[ $setting ] ) );
			}
		}
	}

	/**
	 * Get a description for this gateway.
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	public static function get_description_for_gateway_settings() {
		return esc_html__( 'CCBill is a global payment gateway that enables online businesses to accept credit card, debit card, and ACH payments while offering built-in fraud protection, subscription billing management, and compliance with international regulations.', 'pmpro-ccbill' );
	}

	/**
	 * Display fields for this gateway's options.
	 *
	 * @since 1.8
	 */
	static function pmpro_payment_option_fields( $values, $gateway ) {
        _deprecated_function( __METHOD__, '3.5' );
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

	/**
	 * Send the user to CCBill to pay.
	 *
	 * @param MemberOrder $order  MemberOrder object for this checkout.
	 * @since TBD
	 */
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
		$initial_subtotal = $order->subtotal;
		$initial_tax = $order->getTaxForPrice( $initial_subtotal );
		$initial_payment_amount = pmpro_round_price( (float) $initial_subtotal  + (float) $initial_tax );

		// Now, let's handle the recurring payments.
		$level = $order->getMembershipLevelAtCheckout();
		if ( pmpro_isLevelRecurring( $level ) ) {

			$recurring_price = number_format( $level->billing_amount, 2, ".", "" );

			$recurring_period = '';

			//TODO: Add a warning to the admin page that billing periods for CCBill
			//are best set in days, so there is no confusion over off by 1 etc.

			//figure out days based on period
			$cycle_period = $level->cycle_period;
			$cycle_number = $level->cycle_number;

			switch ( $cycle_period ) {
				case "Week":
					$recurring_period = 7 * $cycle_number;
					break;
				case "Month":
					$recurring_period = 30 * $cycle_number;
					break;
				case "Year":
					$recurring_period = 365 * $cycle_number;
					break;
				default:
				case "day":
					$recurring_period = 1 * $cycle_number;
				break;
			}

			$number_of_rebills = '';

			if ( ! empty( $level->billing_limit ) ) {
				$number_of_rebills = $level->billing_limit;
			} else {
				$number_of_rebills = 99; //means unlimited
			}

			$ccbill_args['recurringPrice'] = $recurring_price;
			$ccbill_args['recurringPeriod'] = $recurring_period;
			$ccbill_args['numRebills'] = $number_of_rebills;
			$ccbill_args['initialPrice'] = number_format( $initial_payment_amount, 2, ".", "" );

			//technically, the initial period can be different than the recurring period, but keep it consistant with the other integrations.
			$ccbill_args['initialPeriod'] = $this->get_initialPeriod( $order );
			$ccbill_args['formDigest'] = $this->get_digest( $initial_payment_amount, $ccbill_args['initialPeriod'], $currency_code, $recurring_price, $recurring_period, $number_of_rebills );
			$ccbill_args['pmpro_orderid'] = $order->id;
			$ccbill_args['email'] = $bemail;
		
		} else {	

			// Non-recurring membership
			$ccbill_args['initialPrice'] = number_format( $initial_payment_amount, 2, ".", "" );
			$ccbill_args['initialPeriod'] = $this->get_initialPeriod( $order );
			$ccbill_args['formDigest'] = $this->get_digest( $initial_payment_amount, $ccbill_args['initialPeriod'], $currency_code );
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
		// See if the site is using subaccount options, if so set the query args accordingly.
		$client_subacc = get_option( 'pmpro_ccbill_subaccount_number' );
		if ( ! empty( $client_sub_acc ) ) {
			$qargs["clientSubacc"] = $client_subacc;
			$qargs["usingSubacc"] = $client_subacc;
		}
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
				$cancel_error = sprintf( __( 'Cancellation of subscription id: %s may have failed. Check CCBill Admin to confirm cancellation. Error: %s', 'pmpro-ccbill'), $subscription_id, $error_code );
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
			$pmproemail->subject = sprintf( __( 'Error cancelling subscription at %s', 'pmpro-ccbill' ), get_bloginfo( 'name' ) );
			$pmproemail->data = array( 'body' => $body );
			$pmproemail->sendEmail( get_option( 'admin_email' ) );
		}

		return false;
	}

	/** 
	 * Pull subscription status from CCBill
	 *
	 * @param PMPro_Subscription $subscription The subscription object.
	 * @return string|null Error message is returned if update fails.
	 * @since TBD
	 */
	public function update_subscription_info( $subscription ) {

		// Bail if subscription ID is missing		
		if ( empty( $subscription->get_subscription_transaction_id() ) ) {
			return __( 'Subscription transaction ID is missing.', 'pmpro-ccbill' );
		}

		//build the URL
		$sms_link = "https://datalink.ccbill.com/utils/subscriptionManagement.cgi";

		$qargs = array();
		$qargs["action"] = "viewSubscriptionStatus";

		// See if the site is using subaccount options, if so set the query args accordingly.
		$client_subacc = get_option( 'pmpro_ccbill_subaccount_number' );
		if ( ! empty( $client_sub_acc ) ) {
			$qargs["clientSubacc"] = $client_subacc;
			$qargs["usingSubacc"] = $client_subacc;
		}

		$qargs["subscriptionId"] = $subscription->get_subscription_transaction_id();
		$qargs["clientAccnum"] = get_option( 'pmpro_ccbill_account_number' );
		$qargs["username"] = get_option( 'pmpro_ccbill_datalink_username' );
		$qargs["password"] = get_option( 'pmpro_ccbill_datalink_password' );

		$subscription_link = add_query_arg( $qargs, $sms_link );
		$response = wp_remote_get( $subscription_link );

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );

		//Bail if error
		if ( 200 != $response_code ) {
			$error = sprintf( __( 'Subscription status for subscription id: %s may have failed. Check CCBill Admin to confirm status', 'pmpro-ccbill' ), $subscription->get_subscription_transaction_id() );
			if ( ! empty( $response_message ) ) {
				$error .= ' ' . $response_message;
			}
			return $error;
		}

		// Get response body (CSV format)
		$body = wp_remote_retrieve_body( $response );

		// Bail if empty response
		if ( empty( $body ) ) {
			return __( 'Empty response from CCBill.', 'pmpro-ccbill' );
		}

		/**
		 * CCBill doesn't have a sandbox accountMock CSV response body, uncomment if need  test it.
		 * Change values according to the test scenario you need.
		 * 
		 * $body = <<<CSV
		 * 	"cancelDate","signupDate","chargebacksIssued","timesRebilled","expirationDate","recurringSubscription","subscriptionStatus","refundsIssued","voidsIssued"
		 * 	"20250131","20250131","0","0","20250131","1","0","1","0"
		 * 	CSV;
		 */

		// Parse CSV response
		$lines = explode( "\n", trim( $body ) ); // Split by lines
		if ( count( $lines ) < 2 ) {
			return __( 'Invalid CSV response from CCBill.', 'pmpro-ccbill' );
		}

		$headers = str_getcsv( $lines[0] ); // First row contains column names
		$values = str_getcsv( $lines[1] );  // Second row contains actual data

		// Bail if mismatched columns and values
		if ( count( $headers ) !== count( $values ) ) {
			return __( 'Mismatched CSV columns and values.', 'pmpro-ccbill' );
		}

		// Convert CSV data into an associative array
		$data = array_combine( $headers, $values );
		//Bail if no results
		if ( isset( $data['results'] ) && $data['results'] === "-1" ) {
			return __( 'No data returned from CCBill.', 'pmpro-ccbill' );
		}

		// Bail if not recurring subscription
		if ( isset( $data['recurringSubscription'] ) ) {
			$recurring = intval( $data['recurringSubscription'] );
			if ( ! $recurring ) {
				return __( 'Subscription is not recurring.', 'pmpro-ccbill' );
			}
		}

		// Extract and assign values only if they exist
		$update_array = array();

		// Assign status immediately
		if ( isset( $data['subscriptionStatus'] ) ) {
			$status_code = intval( $data['subscriptionStatus'] );
			if ( $status_code > 1 ) {
				$update_array['status'] = 'active';
			} else {
				$update_array['status'] = 'cancelled';
				if ( isset( $data['cancelDate'] ) && ! empty( $data['cancelDate'] ) ) {
					$update_array['enddate'] = date( 'Y-m-d H:i:s', strtotime( $data['cancelDate'] ) );
				}
			}
		}

		// Extract and format dates if they exist
		if ( isset( $data['signupDate'] ) && ! empty( $data['signupDate'] ) ) {
			$update_array['startdate'] = date( 'Y-m-d H:i:s', strtotime( $data['signupDate'] ) );
		}

		if ( isset( $data['expirationDate'] ) && ! empty( $data['expirationDate'] ) ) {
			$update_array['next_payment_date'] = date( 'Y-m-d H:i:s', strtotime( $data['expirationDate'] ) );
		}

		// Update subscription object
		$subscription->set( $update_array );
	}

	/**
	 * Functionality to process refunds for CCBill. This only supports full refunds.
	 *
	 * @param [type] $success
	 * @param [type] $order
	 * @return void
	 */
	static function process_refund( $success, $order ) {

		if ( empty( $order->payment_transaction_id ) ) {
			return false;
		}

		// Let's set success to false as a default.
		$success = false;

		$transaction_id = $order->payment_transaction_id;

		$ccbill_url = 'https://datalink.ccbill.com/utils/subscriptionManagement.cgi';

		$ccbill_args = array();
		$ccbill_args['action'] = 'refundTransaction';
		$client_subacc = get_option( 'pmpro_ccbill_subaccount_number' );
		if ( ! empty( $client_sub_acc ) ) {
			$ccbill_args['clientSubacc'] = $client_subacc;
			$ccbill_args['usingSubacc'] = $client_subacc;
		}
		$ccbill_args['clientAccnum'] = get_option('pmpro_ccbill_account_number');
		$ccbill_args['username'] = get_option('pmpro_ccbill_datalink_username');
		$ccbill_args['password'] = get_option('pmpro_ccbill_datalink_password');
		$ccbill_args['subscriptionId'] = $transaction_id; //CCBill refers to it as subscriptionId.

		// Make the call to CCBill now.
		$ccbill_url = add_query_arg( $ccbill_args, $ccbill_url );

		$response = wp_remote_get( $ccbill_url );

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );

		if ( $response_code !== 200 ) {
			$order->notes = trim( $order->notes . ' ' . __( 'Admin: There was a problem processing the refund.', 'pmpro-ccbill' ) . ' ' . $response_message );
			$order->SaveOrder();
			return false;
		}

		$response_body = wp_remote_retrieve_body( $response );

		$response_values = explode( "\n", trim( $response_body ) ); // Split by response_values
		if ( count( $response_values ) < 2 ) {
			return __( 'Invalid CSV response from CCBill.', 'pmpro-ccbill' );
		}

		// Strip out additional double quotation marks so we can cast to an int to compare.
		$return_value = (int) str_replace( '"', '', $response_values[1] );

		// Refund was successful lets send an email.
		if ( intval( $return_value ) === 1 ) {
			$success = true;
			$order->status = 'refunded';
			//send an email to the member
			$myemail = new PMProEmail();
			$myemail->sendRefundedEmail( $user, $order );

			//send an email to the admin
			$myemail = new PMProEmail();
			$myemail->sendRefundedAdminEmail( $user, $order );
		} else {
			$order->notes = trim( $order->notes . ' ' . __( 'Admin: We encountered an issue processing the refund. Please review it in CCBill and complete the refund there.', 'pmpro-ccbill' ));
		}

		$order->SaveOrder();

		return $success;
	}
}
