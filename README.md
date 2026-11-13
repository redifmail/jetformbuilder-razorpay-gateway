# JetFormBuilder Razorpay Gateway

Accept one-time Razorpay payments directly from JetFormBuilder forms.

Built by [MK Designs](https://www.mkdeesigns.com).

## Why we built it

A registration, donation or booking form often needs a payment step as part of the submission. We built this integration to connect Razorpay checkout with JetFormBuilder's payment records and post-payment actions, keeping the form and payment workflow together.

The plugin opens Razorpay checkout, checks the returned payment against the saved order, and passes the result back to JetFormBuilder. Webhooks and an admin reconciliation tool help follow up on payments when the browser return is interrupted.

## What it does

- Adds Razorpay as a payment gateway in JetFormBuilder.
- Connects successful payments to the form's configured paid actions.
- Checks order ID, payment status, amount and currency during browser-return verification.
- Shows Razorpay transaction details inside the JetFormBuilder payment record.
- Saves details received through browser returns, webhooks and reconciliation.
- Uses JetFormBuilder's server-side amount resolver when **Secure payment amount** is enabled.

## Getting started

1. Install and activate JetFormBuilder and this plugin.
2. Add your Razorpay credentials in the gateway settings.
3. Select Razorpay in your form's gateway settings and choose the amount field.
4. Enable **Secure payment amount** and use a supported, server-verifiable amount source.
5. Add **Save Form Record** and place the actions that depend on payment under the gateway success event.
6. Check the checkout, payment record and resulting actions with your form configuration. Razorpay Test Mode is useful when trying changes.

Existing forms keep their own settings, so check **Secure payment amount** on each payment form.

## What's new in 0.3.0

This update focuses on matching the payment returned by Razorpay to the order stored in JetFormBuilder. Amount or currency mismatches are recorded as `AMOUNT_MISMATCH`. It also fixes a missing `Gateway_Context` import and updates the integration to JetFormBuilder's `JFB_Modules` namespaces.

Version 0.2.9 added the Razorpay details panel, bringing transaction information into the payment record for easier troubleshooting.

See the [changelog](CHANGELOG.md) for version history and [releases](https://github.com/redifmail/jetformbuilder-razorpay-gateway/releases) for tagged downloads.

## Compatibility and known issues

Developed for JetFormBuilder 3.6.4.1 and PHP 7.4+. Live checkout has been tested.

The following edge cases are under investigation:

- Browser returns and webhooks arriving together may run paid actions more than once.
- A browser failure callback may close a pending record before a later payment confirmation arrives.
- Missing amount or currency values, and forms using different credential sets, need additional checks.

This version handles one-time payments. Recurring subscriptions are a possible future addition.

## Development

The repository includes the plugin source, bundled Razorpay SDK and syntax-check workflow. See [contributing](CONTRIBUTING.md) for bug reports and changes, and [security reporting](SECURITY.md) for sensitive findings.

Third-party licenses are included with their dependencies. A license for the project's own code is still to be selected; see [third-party details](docs/THIRD-PARTY.md).
