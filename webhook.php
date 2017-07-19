<?php

global $wpdb, $gateway_environment, $logstr;

/*
$fh = fopen("HV_DEBUG_CCBILL.txt", "w");
fwrite($fh, print_r($_POST, true));
fwrite($fh, print_r($_GET, true));
fclose($fh);*/

$event_type = pmpro_getParam('eventType', 'REQUEST');

$response = array();

foreach($_REQUEST as $key => $value)
{
	$response[$key] = $value;
}

//Full reference of event types and responses:
//https://kb.ccbill.com/Webhooks+User+Guide
switch($event_type)
{
	case 'NewSaleSuccess':
		
		$order_id = $response['X-pmpro_orderid'];
		$morder = new MemberOrder( $order_id );
		$morder->getMembershipLevel();
		$morder->getUser();
		
		if (pmpro_ccbill_ChangeMembershipLevel( $response, $morder ) ) {
			//Log the event
		}
		
	break;

	case 'NewSaleFailure':
		//Just a notification. They should have already been denied at CCBill
	break;

/*
	case 'UpgradeSuccess':
	break;

	case 'UpSaleSuccess':
	break;

	case 'UpSaleFailure':
	break;

	case 'CrossSaleSuccess':
	break;

	case 'CrossSaleFailure':
	break;*/

	case 'Cancellation':
	break;

	case 'Expiration':
	break;

	case 'BillingDateChange':
	break;

	case 'CustomerDataUpdate':
	break;

	case 'RenewalSuccess':
		
		$order_id = $response['X-pmpro_orderid'];
		
		$morder = new MemberOrder( $order_id );
		$morder->getMembershipLevel();
		$morder->getUser();
		
		pmpro_ccbill_ChangeMembershipLevel($response, $morder);
		
	break;

	case 'Renewal Failure':
	break;

/*
	case 'Chargeback':
	break;

	case 'Return':
	break;

	case 'Refund':
	break;

	case 'Void':
	break;
 *
 */

	
}

function pmpro_ccbill_ChangeMembershipLevel($response, $morder)
{
	//filter for level
	$morder->membership_level = apply_filters("pmpro_ccbill_handler_level", $morder->membership_level, $morder->user_id);
	
	//set the start date to current_time('mysql') but allow filters (documented in preheaders/checkout.php)
	$startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time('mysql') . "'", $morder->user_id, $morder->membership_level);
	//fix expiration date
	
	if(!empty($morder->membership_level->expiration_number))
	{
		$enddate = "'" . date_i18n("Y-m-d", strtotime("+ " . $morder->membership_level->expiration_number . " " . $morder->membership_level->expiration_period, current_time("timestamp"))) . "'";
	}
	else
	{
		$enddate = "NULL";
	}
	//filter the enddate (documented in preheaders/checkout.php)
	$enddate = apply_filters("pmpro_checkout_end_date", $enddate, $morder->user_id, $morder->membership_level, $startdate);
	
	//get discount code
	$morder->getDiscountCode();
	if(!empty($morder->discount_code))
	{
		//update membership level
		$morder->getMembershipLevel(true);
		$discount_code_id = $morder->discount_code->id;
		}
	else
		$discount_code_id = "";
		
	//custom level to change user to
	$custom_level = array(
		'user_id' => $morder->user_id,
		'membership_id' => $morder->membership_level->id,
		'code_id' => $discount_code_id,
		'initial_payment' => $morder->membership_level->initial_payment,
		'billing_amount' => $morder->membership_level->billing_amount,
		'cycle_number' => $morder->membership_level->cycle_number,
		'cycle_period' => $morder->membership_level->cycle_period,
		'billing_limit' => $morder->membership_level->billing_limit,
		'trial_amount' => $morder->membership_level->trial_amount,
		'trial_limit' => $morder->membership_level->trial_limit,
		'startdate' => $startdate,
		'enddate' => $enddate);
	
	global $pmpro_error;
	
	if(!empty($pmpro_error))
	{
		echo $pmpro_error;
	//	inslog($pmpro_error);
	}
	if( pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false )
	{
		$txn_id = $response['transactionId'];
		$sub_id = $response['subscriptionId'];
		$card_type = $response['cardType'];
		$card_num = $response['last4'];
		$card_exp = $response['expDate'];
		
		$card_exp_month = substr($card_exp, 0, 2);
		$card_exp_year = '20'.substr($card_exp, 2);
		
		//update order status and transaction ids
		$morder->status = "success";
		$morder->payment_transaction_id = $txn_id;
		$morder->subscription_transaction_id = $sub_id;
		$morder->cardtype = $card_type;
		$morder->accountnumber = 'XXXXXXXXXXXX'.$card_num;
		$morder->expirationmonth = $card_exp_month;
		$morder->expirationyear = $card_exp_year;
		
	
		$morder->saveOrder();
		
		//add discount code use
		if(!empty($discount_code) && !empty($use_discount_code))
		{
			$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $morder->user_id . "', '" . $morder->id . "', '" . current_time('mysql') . "')");
		}
		//save first and last name fields
		if(!empty($_POST['firstName']))
		{
			$old_firstname = get_user_meta($morder->user_id, "first_name", true);
			if(!empty($old_firstname))
				update_user_meta($morder->user_id, "first_name", $_POST['first_name']);
		}
		if(!empty($_POST['lastName']))
		{
			$old_lastname = get_user_meta($morder->user_id, "last_name", true);
		
			if(!empty($old_lastname))
				update_user_meta($morder->user_id, "last_name", $_POST['last_name']);
		}
		//hook
		do_action("pmpro_after_checkout", $morder->user_id);
		//setup some values for the emails
		if(!empty($morder))
			$invoice = new MemberOrder($morder->id);
		else
			$invoice = NULL;
		
		$user = get_userdata($morder->user_id);
		if(empty($user))
			return false;
		$user->membership_level = $morder->membership_level;		//make sure they have the right level info
		//send email to member
		$pmproemail = new PMProEmail();
		$pmproemail->sendCheckoutEmail($user, $invoice);
		//send email to admin
		$pmproemail = new PMProEmail();
		$pmproemail->sendCheckoutAdminEmail($user, $invoice);
		return true;
	}
	
	else
		return false;	
	
}




	   
