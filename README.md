# JetFormBuilder Razorpay Gateway

> **Pre-release source snapshot, not production-approved.** This repository candidate preserves the supplied v0.3.0 implementation. Payment-lifecycle security review and Razorpay Test Mode integration tests are still outstanding. No stable release or compatibility certification is implied.


Author: MK Designs  
Website: https://www.mkdeesigns.com

Razorpay payment gateway integration for JetFormBuilder form payments such as event registration, donations, application fees, booking fees, and similar one-time form submissions.

## Previously reported compatibility

The existing documentation names JetFormBuilder **3.6.4.1** and declares **PHP 7.4+**. These are historical project claims, **not independently reproduced integration tests in this review**. PHP syntax passed locally on PHP 8.4; current JetFormBuilder 3.6.8 requires new testing. See [test matrix](docs/TEST-MATRIX.md) and [release checklist](docs/RELEASE-CHECKLIST.md).

The browser-return, webhook recovery, and admin reconciliation recovery paths use JetFormBuilder's gateway module API. Recheck these paths after major JetFormBuilder gateway-module changes.

## Secure payment amount

JetFormBuilder 3.6.4.1 adds the **Secure payment amount** form setting. This plugin uses JetFormBuilder's server-side trusted price resolver automatically when that setting is enabled.

For every existing Razorpay form:

1. Open the form editor.
2. Open **Gateways Settings**.
3. Enable **Secure payment amount**.
4. Use a supported amount source, such as a numeric Calculated Field, a non-rendered trusted Hidden Field, or Select/Radio/Checkbox options with calculate values.
5. Test successful, cancelled, and failed payments in Razorpay Test Mode before deploying to production.

Do not automatically enable this setting in the database. JetFormBuilder intentionally leaves it disabled on existing forms because unsupported client-controlled amount fields will be rejected once protection is enabled.

## Scope

This plugin does not add WooCommerce order logic, cart logic, subscriptions, refund UI, manual capture workflows, or a separate commerce dashboard. It stays within JetFormBuilder payment records and gateway success/failure actions.

## v0.3.0

- Tested with JetFormBuilder 3.6.4.1 and its Secure payment amount feature.
- Keeps using JetFormBuilder's server-side amount resolver through the gateway base class.
- Verifies the fetched Razorpay payment order ID, status, amount, and currency against the stored JetFormBuilder payment row before browser-return completion.
- Records an `AMOUNT_MISMATCH` payment status when the fetched amount or currency does not match.
- Fixes the missing `Gateway_Context` import in the browser-return verification path.
- Migrates gateway integrations from JetFormBuilder's deprecated namespace aliases to the current `JFB_Modules` namespaces.

## v0.2.9

- Adds a Razorpay-only admin panel on the JetFormBuilder single payment screen.
- The panel shows saved Razorpay transaction data such as Payment ID, Order ID, status, method, UPI VPA, Bank RRN, Razorpay fee, tax, completion source, and webhook event when available.
- Extra Razorpay payment details are saved from browser-return, webhook, and admin reconciliation completion paths.
- This does not change the payment flow and does not add WooCommerce/order/cart logic.

## Community testing checklist

Before testing on a live site, install this on a staging site first. Add Razorpay test credentials in JetFormBuilder payment gateway settings, create a JetFormBuilder form with Razorpay selected as the payment gateway, select an amount field, add Save Form Record, and place paid actions under the gateway success event.

Recommended tests:

- AJAX form payment success.
- Non-AJAX or reload-based form payment success.
- Failed or cancelled checkout.
- Tampered submitted amount with Secure payment amount enabled.
- Payer field mapping and shipping field mapping.
- Optional Razorpay webhook recovery using `payment.captured` and `payment.authorized`.
- Admin reconciliation for old `CREATED` payment records.

Known limitation: subscription support is not included in this version.

## Repository and distribution status

This is a local GitHub source candidate, not a tagged release. Original source files are kept intact. The project code currently has **no explicit redistribution license** selected, pending an ownership/licensing decision. Bundled third-party license files are retained; see [third-party information](docs/THIRD-PARTY.md).

The syntax-check workflow is a starting point and is not evidence that payment processing is secure or functional.
