=== Paid Memberships Pro - CCBill Add On ===
Contributors: strangerstudios
Tags: paid memberships pro, payment gateway, ccbill
Requires at least: 5.5
Tested up to: 6.8
Stable tag: 1.0.2

Adds the ability to accept payments using the CCBill Payment Gateway

== Description ==

Adds CCBill as a payment gateway to your list of accepted payment gateways. CCBill makes use of an off-site payment method process. This Add On is currently in Beta. 

[Read the full documentation for the CCBill Add On](https://www.paidmembershipspro.com/add-ons/ccbill-payment-gateway/)

= Official Paid Memberships Pro Add On =

This is an official Add On for [Paid Memberships Pro](https://www.paidmembershipspro.com), the most complete member management and membership subscriptions plugin for WordPress.

== Installation ==

1. Make sure you have the Paid Memberships Pro plugin installed and activated.
1. Upload the `pmpro-ccbill` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.

Navigate to Memberships > Settings > Payment Gateways & SSL and select the CCBill payment gateway. You will then need to fill out the required account credentials to connect your website to the payment gateway.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the GitHub issue tracker here: https://github.com/strangerstudios/pmpro-ccbill/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at http://www.paidmembershipspro.com for more documentation and our support forums.

== Changelog ==
= 1.0.2 - 2025-07-10 =
* ENHANCEMENT: Improved support for Paid Memberships Pro 3.5+ and payment gateway settings. (@JarrydLong)

= 1.0.1 - 2025-05-07 =
* BUG FIX: Fixed an issue where checkout would not complete orders from "token" status even if payments are successful.
* BUG FIX: Better supported sandbox mode checkouts to work with subscriptions and one-time payments.

= 1.0 - 2025-04-14 =
* ENHANCEMENT: Added refund logic for CCBill. This allows you to process refunds directly from the Paid Memberships Pro dashboard.
* ENHANCEMENT: Added support for "Sync with Gateway" functionality.
* BUG FIX: Fixed an issue where cancellations would always cancel immediately. This now correctly gives the member the remaining days of their membership when cancelling.
* BUG FIX: Fixed minor warnings for PHP 8.0+ environments.
* REFACTOR: Refactored Add On code to work how other gateways work and utilize the newer Paid Memberships Pro codebase.

= 0.5.1 - 2024-08-14 =
* BUG FIX: Fixed an issue where recurring orders would show an incorrect date. (Thanks @misfist)

= 0.5 - 2024-07-15 =
* ENHANCEMENT: Added improved support for renewals and renewal failures. (Thanks @misfist for your contribution)
* ENHANCEMENT: Added support for "Expired" webhook event types, when a subscription has expired. (@andrewlimaza)
* REFACTOR: Adjusted the HTML for the checkout button to support newer versions of PMPro and improved accessibility. (@kimcoleman)

= 0.4.2 - 2023-10-05 =
* BUG FIX: Fixed an issue where the initialPeriod was incorrectly set for levels with an expiration.

= 0.4.1 - 2023-08-15 =
* BUG FIX: Fixed an issue where webhook wasn't being verified correctly.

= 0.4 - 2023-07-27 =
* SECURITY: Fixed a minor issue to verify the webhook received is related to the correct CCBill account.

= 0.3 - 2023-05-05 =
* BUG FIX: Fixed issue where cancellations in PMPro were not being sent to CCBill.
* BUG FIX: Fixed issue where "failed cancellation" emails may be incorrectly sent.
* BUG FIX: Handling undefined variable in cancellation webhook code.

= 0.2 - 2023-04-09 =
* BUG FIX: Only cancel relevant URL on sub cancellation.
* BUG FIX: Fixed calculation of the initialPeriod value passed to CCBill.
* BUG FIX: Fixed errors in the webhook code.

= 0.1 - 2022-10-05 =
* Initial Release