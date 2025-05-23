<?php

//set this in your wp-config.php for debugging
//define( 'PMPRO_CCBILL_DEBUG', true );

global $wpdb, $gateway_environment, $logstr;

$logstr = ''; //will put debug info here and write to ccbill_webhook_log.txt

if ( ! function_exists( 'pmpro_getParam' ) ){
	return;
}

$event_type = pmpro_getParam('eventType', 'REQUEST');

$response = array();

foreach ( $_REQUEST as $key => $value ) {
	$response[ $key ] = sanitize_text_field( $value );
}

// Make sure that the response matches the account number saved to ensure it's for the same account/subscription.
if ( empty( $response['clientAccnum'] ) || $response['clientAccnum'] !== get_option( 'pmpro_ccbill_account_number', true ) ) {
	pmpro_ccbill_webhook_log( __( "There was an error processing your CCBill webhook. Account number doesn't match the one on record.", 'pmpro-ccbill' ) );
	pmpro_ccbill_Exit();
}

// Full reference of event types and responses:
// https://ccbill.com/doc/webhooks-overview
switch ( $event_type ) {

	case 'NewSaleSuccess':
		$order_id = sanitize_text_field( $response['X-pmpro_orderid'] );
		$morder = new MemberOrder( $order_id );

		// Let's save the order data that may be needed. Ensure that there is a recurring amount passed in, sandbox passes this even for one-time payments.
		if ( ! empty( $response['subscriptionId'] ) && (  isset( $response['subscriptionRecurringPrice'] ) && intval( $response['subscriptionRecurringPrice'] ) > 0 ) ) {
			$morder->subscription_transaction_id = sanitize_text_field( $response['subscriptionId'] );
		}

		if ( ! empty( $response['transactionId'] ) ) {
			$morder->payment_transaction_id = sanitize_text_field( $response['transactionId'] );
		}

		$morder->saveOrder();

		//run the function to complete checkout
		if ( pmpro_ccbill_ChangeMembershipLevel( $morder ) ) {
			//Log the event
			pmpro_ccbill_webhook_log( sprintf( __( "Checkout processed (%s) success!", 'pmpro_ccbill'), $morder->code ) );
		}

		pmpro_ccbill_Exit();
		
	break;

	case 'Expiration':
	case 'Cancellation':
		
		$subscription_id = sanitize_text_field( $response['subscriptionId'] );

		pmpro_ccbill_webhook_log( pmpro_handle_subscription_cancellation_at_gateway( $subscription_id, 'ccbill', 'live' ) );
		pmpro_ccbill_Exit();
	break;

	case 'RenewalSuccess':
		$status = 'success';
		pmpro_ccbill_AddRenewal( $response, $status );
		pmpro_ccbill_Exit();

		break;

	case 'RenewalFailure':
		$status     = 'error';
		pmpro_ccbill_AddRenewal( $response, $status );
		pmpro_ccbill_Exit();

		break;

	default:
		do_action('pmpro_ccbill_other_webhook_events', $event_type);
		pmpro_ccbill_Exit();
		
	break;	
}

/**
 *  Change Membership Level for CCBill.
 * * @param  MemberOrder $morder
 * @return bool
 * @since 0.1
 */
function pmpro_ccbill_ChangeMembershipLevel( $morder ) {
	pmpro_pull_checkout_data_from_order( $morder );
 	return pmpro_complete_async_checkout( $morder );
}

/**
 * Add Renewal Order
 *
 * @param  array  $response
 * @param  string $status
 * @return void
 */
function pmpro_ccbill_AddRenewal( array $response, $status = 'success' ) : void {
	$transaction_id  = $response['transactionId'];
	$subscription_id = $response['subscriptionId'];
	$timestamp       = is_numeric( $response['timestamp'] ) ? $response['timestamp'] : strtotime( $response['timestamp'] ); // Convert to a timestamp if it's not already passed through.
	$payment_type    = $response['paymentType'];
	$card_type       = $response['cardType'];
	$renewal_date    = $response['renewalDate'];
	
	$morder          = new MemberOrder();
	$morder->getMemberOrderByPaymentTransactionID( $transaction_id );

	if ( empty( $morder->id ) ) {
		/**
		 * Order doesn't exist
		 */
		$old_order    = new MemberOrder();
		$old_order->getLastMemberOrderBySubscriptionTransactionID( $subscription_id );

		pmpro_ccbill_webhook_log( 'old_order: ' . json_encode( $old_order ) );

		// Original subscription order cannot be found. Let's Bail.
		if ( empty( $old_order ) || empty( $old_order->id ) ) {
			pmpro_ccbill_webhook_log( sprintf( __( "Couldn't find the original subscription: (%s).", 'pmpro_ccbill' ), $subscription_id ) );
			pmpro_ccbill_Exit();
		}

		$user_id = $old_order->user_id;
		$user    = get_userdata( $user_id );

		// No user found for this order anymore.
		if ( empty( $user ) ) {
			pmpro_ccbill_webhook_log( sprintf( __( "Couldn't find the old order's user. Order ID (%s).", 'pmpro_ccbill' ), $old_order->id ) );
			pmpro_ccbill_Exit();
		}

		$user->membership_level = pmpro_getMembershipLevelForUser( $user_id );

		// Log the user email only for security reasons.
		pmpro_ccbill_webhook_log( 'WP User Email: ' . json_encode( $user->user_email ) );

		// Create a new order now.
		$order = new MemberOrder();
		$order->user_id                     = $user_id;
		$order->status                      = $status;
		$order->membership_id               = $user->membership_level->id;
		$order->payment_transaction_id      = $transaction_id;
		$order->subscription_transaction_id = $subscription_id;
		$order->gateway                     = get_option( 'pmpro_gateway' );
		$order->gateway_environment         = get_option( 'pmpro_gateway_environment' );
		$order->timestamp                   = $timestamp;
		$order->payment_type                = $payment_type;
		$order->cardtype                    = $card_type;

		$order->find_billing_address();

		if ( 'error' === $status ) {

			do_action( 'pmpro_subscription_payment_failed', $old_order );

			$order->notes = sprintf( __( 'Renewal failed: %s (%s). Retry on %s.', 'pmpro_ccbill' ) , $response['failureReason'], $response['failureCode'], $response['nextRetryDate'] );
			$order->saveOrder();

			// Email the customer about this failure.
			$pmproemail = new PMProEmail();
			$pmproemail->sendBillingFailureEmail( $user, $order );

			// Email admin so they are aware of the failure
			$pmproemail = new PMProEmail();
			$pmproemail->sendBillingFailureAdminEmail(get_bloginfo("admin_email"), $order);

			// Write to the log
			pmpro_ccbill_webhook_log( sprintf( __( 'Renewal failed (%s) for subscription # (%s).', 'pmpro_ccbill' ), $response['failureReason'], $subscription_id ) );
			pmpro_ccbill_Exit();

		} else {
			$card_number    = $response['last4'];
			$card_exp       = $response['expDate'];
			$total          = $response['accountingAmount'];
			$card_exp_month = substr( $card_exp, 0, 2 );
			$card_exp_year  = '20' . substr( $card_exp, 2 );

			$order->accountnumber   = 'XXXXXXXXXXXX' . $card_number;
			$order->expirationmonth = $card_exp_month;
			$order->expirationyear  = $card_exp_year;
			$order->subtotal        = $total;
			$order->total           = $total;
		}

		// Save the order before sending the email.
		$order->saveOrder();

		if ( $order->id ) {
			$order->getMemberOrderByID( $order->id );

			/**
			 * Send customer email
			 */
			$email = new PMProEmail();
			$email->sendInvoiceEmail( $user, $order );

			pmpro_ccbill_webhook_log( sprintf( __( 'Order created (%1$s) for subscription # (%2$s).', 'pmpro_ccbill' ), $order->id, $subscription_id ) );

			do_action( 'pmpro_subscription_payment_completed', $order, $response );
		}

	} else {
		/**
		 * Order exists, log and exit
		 */
		pmpro_ccbill_webhook_log( sprintf( __( 'An order with that payment ID (%s) already exists.', 'pmpro_ccbill' ), $transaction_id ) );
	}
	
	pmpro_ccbill_Exit();

}

/**
 * Cancel level for a subscription. Deprecated, call pmpro_handle_subscription_cancellation_at_gateway instead.
 *
 * @param MemberOrder $morder The order object.
 * return bool True if the cancellation was successful, false otherwise.
 * @since TBD
 * @deprecated TBD
 */
function pmpro_ccbill_RecurringCancel( $morder ) {
	_deprecated_function( __FUNCTION__, 'TBD', 'pmpro_handle_subscription_cancellation_at_gateway' );

	global $pmpro_error;
	$worked = pmpro_cancelMembershipLevel( $morder->membership_level->id, $morder->user_id, 'inactive' );

	if ( $worked === true ) {
		//send an email to the member
		$myemail = new PMProEmail();
		$myemail->sendCancelEmail();
		//send an email to the admin
		$myemail = new PMProEmail();
		$myemail->sendCancelAdminEmail( $morder->user, $morder->membership_level->id );
		
		pmpro_ccbill_webhook_log( sprintf( __( "Subscription Cancelled (%s)", 'pmpro-ccbill'), $morder->csubscription_transaction_id ) );

		return true;
	} else {
		return false;
	}
}

/*
	Add message to webhook string
*/
function pmpro_ccbill_webhook_log( $s ) {
	global $logstr;
	$logstr .= "\t" . $s . "\n";
}
/*
	Output webhook log and exit;
*/
function pmpro_ccbill_Exit( $redirect = false ) {
	global $logstr;
	$logstr = var_export( $_REQUEST, true ) . sprintf( __( 'Logged On: %s', 'pmpro-ccbill' ), date_i18n("m/d/Y H:i:s") ) . "\n" . $logstr . "\n-------------\n";
	//log in file or email?
	if ( defined( 'PMPRO_CCBILL_DEBUG' ) && PMPRO_CCBILL_DEBUG === 'log' ) {
		//file
		$loghandle = fopen(PMPRO_CCBILL_DIR. "/logs/ccbill_webhook.txt", "a+");
		fwrite($loghandle, $logstr);
		fclose($loghandle);
	} elseif ( defined( 'PMPRO_CCBILL_DEBUG' ) && false !== PMPRO_CCBILL_DEBUG ) {
		//email
		if ( strpos( PMPRO_CCBILL_DEBUG, "@" ) ) {
			$log_email = PMPRO_CCBILL_DEBUG;	//constant defines a specific email address
		} else {
			$log_email = get_option("admin_email");
		}

		wp_mail($log_email, get_option("blogname") . ' ' . __( "CCBill Webhook Log", 'pmpro-ccbill' ), nl2br($logstr));
	}

	if ( !empty( $_REQUEST['pmpro_orderid'] ) ){
		//Coming back from the gateway, lets redirect back to membership confirmation
		$morder = new MemberOrder( intval( $_REQUEST['pmpro_orderid'] ) );
		
		if ( !empty( $morder ) ) {
			$redirect = pmpro_url( "confirmation", "?level=" . $morder->membership_id );
		}
	}

	if ( ! empty( $redirect ) ) {
		wp_redirect( $redirect );
	}

	exit;
}
