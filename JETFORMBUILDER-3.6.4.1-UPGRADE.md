# JetFormBuilder 3.6.4.1 upgrade notes

## What changed

JetFormBuilder now provides a form-level **Secure payment amount** setting. When enabled, JetFormBuilder resolves the configured price field again on the server instead of trusting the browser-submitted amount.

The Razorpay gateway already calls JetFormBuilder's base gateway methods `set_price_field()` and `set_price_from_filed()`. Version 0.3.0 keeps that integration and adds final Razorpay payment verification against the saved JetFormBuilder payment row.

## Required administrator action

Existing forms are not changed automatically. Open each Razorpay payment form, enable **Secure payment amount** in **Gateways Settings**, confirm the selected amount field is supported, save the form, and test it in Razorpay Test Mode.

## Supported amount sources

Use the source rules displayed by JetFormBuilder. Typical supported sources are:

- Calculated Field with Value type set to Number.
- Hidden Field with Render in HTML disabled and a trusted server-resolvable source.
- Select, Radio, or Checkbox fields with reproducible calculate values.

Typical unsupported sources are browser-editable Number or Text fields, visible Hidden fields, query-string values, and unsigned post-context values.

## Verification added in version 0.3.0

Before paid actions are resumed, the plugin now checks the fetched Razorpay payment against the stored JetFormBuilder payment row:

- Payment status is captured or authorized.
- Razorpay order ID matches.
- Amount in paise matches the saved amount.
- Currency matches.

Amount or currency mismatches are saved as `AMOUNT_MISMATCH`. Webhook mismatches are acknowledged with HTTP 200 so Razorpay does not repeatedly retry a permanent mismatch.

## Staging test matrix

1. Normal payment with a supported Calculated Field.
2. Browser-submitted amount changed with developer tools. Razorpay must still receive the server-resolved amount.
3. Unsupported Number Field with protection enabled. JetFormBuilder must reject the payment before creating an order.
4. Cancelled Razorpay popup.
5. Failed payment return.
6. Successful browser return.
7. Successful webhook recovery.
8. Admin reconciliation of a `CREATED` payment.
