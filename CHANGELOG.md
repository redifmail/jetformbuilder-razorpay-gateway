# Changelog

## 0.3.0

- Added compatibility guidance for JetFormBuilder 3.6.4.1 Secure payment amount.
- Added browser-return verification of Razorpay order ID, payment status, amount, and currency against the stored JetFormBuilder payment row.
- Added `AMOUNT_MISMATCH` persistence for amount or currency mismatches.
- Fixed the missing `Gateway_Context` import in browser-return processing.
- Replaced deprecated JetFormBuilder gateway namespace aliases with current `JFB_Modules` namespaces.

## 0.2.9

- Added Razorpay payment details to the JetFormBuilder single payment screen.
- Saved additional payment details from browser return, webhook, and reconciliation paths.
