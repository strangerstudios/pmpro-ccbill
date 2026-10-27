<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
$stored_account_number = trim( (string) get_option( 'pmpro_ccbill_account_number' ) );
$received_account_number = trim( (string) $response['clientAccnum'] );

if ( empty( $received_account_number ) || $received_account_number !== $stored_account_number ) {
	pmpro_ccbill_webhook_log( sprintf(
		"There was an error processing your CCBill webhook. Account number doesn't match the one on record. Received: '%s', Expected: '%s'.",
		$received_account_number,
		$stored_account_number
	) );
	pmpro_ccbill_Exit();
}

// CCBill doesn't sign webhooks, so make sure that this request really came from CCBill before processing it.
$verification_error = pmpro_ccbill_verify_webhook_source( $event_type, $response );
if ( ! empty( $verification_error ) ) {
	pmpro_ccbill_webhook_log( $verification_error );
	pmpro_ccbill_Exit();
}

// Full reference of event types and responses:
// https://ccbill.com/doc/webhooks-overview
switch ( $event_type ) {

	case 'NewSaleSuccess':
		$order_id = sanitize_text_field( $response['X-pmpro_orderid'] );
		$morder = new MemberOrder( $order_id );

		pmpro_ccbill_webhook_log( sprintf( 'NewSaleSuccess received. X-pmpro_orderid: %s, Loaded order ID: %s, Loaded order user_id: %s', $order_id, $morder->id, $morder->user_id ) );

		// Let's save the order data that may be needed. Ensure that there is a recurring amount passed in, sandbox passes this even for one-time payments.
		if ( ! empty( $response['subscriptionId'] ) && (  isset( $response['subscriptionRecurringPrice'] ) && intval( $response['subscriptionRecurringPrice'] ) > 0 ) ) {
			$morder->subscription_transaction_id = sanitize_text_field( $response['subscriptionId'] );
		}

		if ( ! empty( $response['transactionId'] ) ) {
			$morder->payment_transaction_id = sanitize_text_field( $response['transactionId'] );
		}

		$morder->saveOrder();

		// Remember the CCBill subscription ID even for one-time payments so that it can't be reused for another order.
		if ( ! empty( $morder->id ) && ! empty( $response['subscriptionId'] ) ) {
			update_pmpro_membership_order_meta( $morder->id, 'ccbill_subscription_id', $response['subscriptionId'] );
		}

		//run the function to complete checkout
		if ( pmpro_ccbill_ChangeMembershipLevel( $morder, $response ) ) {
			//Log the event
			pmpro_ccbill_webhook_log( sprintf( __( "Checkout processed (%s) success!", 'pmpro_ccbill'), $morder->code ) );
		} else {
			// Named fields for a quick diagnosis; pmpro_ccbill_Exit() already dumps the full $_REQUEST below.
			pmpro_ccbill_webhook_log( sprintf(
				'Checkout FAILED to assign a membership level. Order ID: %s, code: %s, user_id: %s, membership_id: %s, X-pmpro_levelid: %s.',
				$morder->id,
				$morder->code,
				$morder->user_id,
				$morder->membership_id,
				isset( $response['X-pmpro_levelid'] ) ? $response['X-pmpro_levelid'] : '(not set)'
			) );
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
 * Make sure that a webhook request came from CCBill.
 *
 * Requests from CCBill's webhook IP addresses are accepted. Otherwise (e.g. for sites behind a proxy or CDN),
 * the event must be confirmed through DataLink. If DataLink can't confirm it, the request is rejected.
 *
 * @since TBD
 *
 * @param string $event_type The webhook event type.
 * @param array  $response   The sanitized webhook postback data.
 * @return string An error message if the request could not be verified, or an empty string if it was.
 */
function pmpro_ccbill_verify_webhook_source( $event_type, $response ) {
	global $wpdb;

	// Only REMOTE_ADDR is checked. Forwarding headers can be set by anyone.
	$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	// CCBill's webhook IP ranges are x.x.x.1-254 in these blocks: https://ccbill.com/doc/webhooks-user-guide
	if ( filter_var( $remote_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		$octets = explode( '.', $remote_ip );
		$block  = $octets[0] . '.' . $octets[1] . '.' . $octets[2];
		if ( in_array( $block, array( '64.38.212', '64.38.215', '64.38.240', '64.38.241' ), true ) && $octets[3] >= 1 && $octets[3] <= 254 ) {
			return '';
		}
	}

	// Not from a CCBill IP address, so confirm the subscription through DataLink instead.
	$error_prefix    = sprintf( 'Webhook rejected. The request came from %s, which is not a CCBill IP address, and could not be confirmed through DataLink.', $remote_ip );
	$subscription_id = isset( $response['subscriptionId'] ) ? $response['subscriptionId'] : '';

	if ( empty( get_option( 'pmpro_ccbill_datalink_username' ) ) || empty( get_option( 'pmpro_ccbill_datalink_password' ) ) ) {
		return $error_prefix . ' DataLink credentials are not set.';
	}

	if ( empty( $subscription_id ) ) {
		return $error_prefix . ' No subscriptionId was sent.';
	}

	$data = PMProGateway_CCBill::get_datalink_subscription_status( $subscription_id );
	if ( is_wp_error( $data ) ) {
		return $error_prefix . ' ' . $data->get_error_message();
	}

	// DataLink returns a "results" error code instead of the subscription if it isn't found for this account.
	if ( ( isset( $data['results'] ) && intval( $data['results'] ) < 1 ) || ! isset( $data['subscriptionStatus'] ) ) {
		return sprintf( '%s Subscription %s was not found for this CCBill account.', $error_prefix, $subscription_id );
	}

	if ( 'NewSaleSuccess' === $event_type ) {
		// The sale must not have been voided, refunded or charged back.
		foreach ( array( 'voidsIssued', 'refundsIssued', 'chargebacksIssued' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || 0 !== intval( $data[ $key ] ) ) {
				return sprintf( '%s Subscription %s has %s: %s.', $error_prefix, $subscription_id, $key, isset( $data[ $key ] ) ? $data[ $key ] : '(missing)' );
			}
		}

		$morder = new MemberOrder( isset( $response['X-pmpro_orderid'] ) ? $response['X-pmpro_orderid'] : 0 );
		if ( empty( $morder->id ) ) {
			return $error_prefix . ' The order could not be found.';
		}

		// The subscription must not already be used by another order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$other_order_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT o.id FROM $wpdb->pmpro_membership_orders o
				LEFT JOIN $wpdb->pmpro_membership_ordermeta om ON om.pmpro_membership_order_id = o.id AND om.meta_key = 'ccbill_subscription_id'
				WHERE o.id <> %d AND ( o.subscription_transaction_id = %s OR o.payment_transaction_id = %s OR om.meta_value = %s )
				LIMIT 1",
				$morder->id,
				$subscription_id,
				$subscription_id,
				$subscription_id
			)
		);
		if ( ! empty( $other_order_id ) ) {
			return sprintf( '%s Subscription %s is already used by order #%s.', $error_prefix, $subscription_id, $other_order_id );
		}

		// The subscription must have been created around the time of the order. CCBill's signupDate (YYYYMMDD) isn't in UTC,
		// so allow the day before the order's date (time zones) through 2 days after it (slow checkouts or delayed webhooks).
		$signup_date = ( isset( $data['signupDate'] ) && preg_match( '/^\d{8}$/', $data['signupDate'] ) ) ? strtotime( $data['signupDate'] ) : false;
		$order_date  = strtotime( gmdate( 'Y-m-d', $morder->getTimestamp( true ) ) );
		if ( empty( $signup_date ) || $signup_date < $order_date - DAY_IN_SECONDS || $signup_date > $order_date + 2 * DAY_IN_SECONDS ) {
			return sprintf( '%s Subscription %s signupDate (%s) is not close to the date of order #%s (%s).', $error_prefix, $subscription_id, isset( $data['signupDate'] ) ? $data['signupDate'] : '(missing)', $morder->id, gmdate( 'Ymd', $order_date ) );
		}
	} elseif ( 'RenewalSuccess' === $event_type ) {
		// CCBill must have rebilled the subscription more times than we have recorded renewals for it,
		// so that a subscription can't be replayed to add renewals that weren't paid. The first paid order is the initial sale.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$paid_orders = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->pmpro_membership_orders
				WHERE subscription_transaction_id = %s AND status NOT IN ( 'error', 'token', 'pending', 'review' )",
				$subscription_id
			)
		);
		$recorded_renewals = max( 0, $paid_orders - 1 );
		if ( ! isset( $data['timesRebilled'] ) || intval( $data['timesRebilled'] ) <= $recorded_renewals ) {
			return sprintf( '%s Subscription %s has been rebilled %s times and %d renewals are already recorded.', $error_prefix, $subscription_id, isset( $data['timesRebilled'] ) ? $data['timesRebilled'] : '(missing)', $recorded_renewals );
		}
	}

	return '';
}

/**
 *  Change Membership Level for CCBill.
 * * @param  MemberOrder $morder
 * @param  array        $response The sanitized webhook postback data.
 * @return bool
 * @since 0.1
 */
function pmpro_ccbill_ChangeMembershipLevel( $morder, $response = array() ) {
	global $pmpro_level;

	pmpro_pull_checkout_data_from_order( $morder );

	// Never trust the level ID out of the request itself ($response['X-pmpro_levelid']) as the
	// source of the level to grant -- a webhook postback can be spoofed by anyone who can guess/
	// obtain an order ID, and it's only used below as a tamper/mismatch check for logging.
	// The order's `membership_id` column is set server-side at checkout, before the user is ever
	// sent to CCBill, and can't be influenced by the postback -- so it's the trustworthy fallback.
	if ( empty( $pmpro_level->id ) && ! empty( $morder->membership_id ) ) {
		$fallback_level = pmpro_getLevel( intval( $morder->membership_id ) );

		if ( ! empty( $fallback_level ) ) {
			pmpro_ccbill_webhook_log( sprintf( 'checkout_level order meta was missing for order #%s. Falling back to the order\'s stored membership_id (%s).', $morder->id, $morder->membership_id ) );
			$pmpro_level = $fallback_level;
		}
	}

	if ( empty( $pmpro_level->id ) ) {
		pmpro_ccbill_webhook_log( sprintf( 'No membership level could be determined for order #%s (user #%s). Aborting level change.', $morder->id, $morder->user_id ) );
		return false;
	}

	// Sanity/fraud check: the level ID CCBill echoed back should match what the order was
	// actually created for. A mismatch doesn't change what we grant (we always grant based on
	// the order's own trusted data above), but it's worth flagging for investigation.
	if ( ! empty( $response['X-pmpro_levelid'] ) && intval( $response['X-pmpro_levelid'] ) !== intval( $pmpro_level->id ) ) {
		pmpro_ccbill_webhook_log( sprintf( 'WARNING: X-pmpro_levelid (%s) in the postback does not match the level being granted (%s) for order #%s. Possible tampered/replayed request -- investigate.', $response['X-pmpro_levelid'], $pmpro_level->id, $morder->id ) );
	}

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
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: the raw request is only written to the debug log.
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

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: only used to build the confirmation page redirect.
	if ( !empty( $_REQUEST['pmpro_orderid'] ) ){
		//Coming back from the gateway, lets redirect back to membership confirmation
		$morder = new MemberOrder( intval( $_REQUEST['pmpro_orderid'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: only used to build the confirmation page redirect.
		
		if ( !empty( $morder ) ) {
			$redirect = pmpro_url( "confirmation", "?level=" . $morder->membership_id );
		}
	}

	if ( ! empty( $redirect ) ) {
		wp_redirect( $redirect ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- pmpro_url() is filterable and may legitimately point to another host (e.g. Network Subsite).
	}

	exit;
}
