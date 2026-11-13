# Payment test matrix

The maintainer reports successful live-payment testing. This table tracks a separate, detailed regression review; individual scenario results from those live tests have not been recorded here. Use Razorpay Test Mode to reproduce edge cases and record the versions used.

| Scenario | Expected behavior | Status |
|---|---|---|
| Clean install and gateway registration | Gateway visible, no PHP fatal | Result to record |
| Server-verifiable calculated amount | Correct Razorpay order amount | Result to record |
| Browser-tampered amount | Rejected or resolved from trusted server settings | Result to record |
| AJAX successful payment | Record completed and each paid action once | Result to record |
| Non-AJAX payment | Checkout and return complete properly | Result to record |
| Invalid payment signature | Never triggers paid actions | Result to record |
| Fake browser failure callback | Must not permanently invalidate a genuine order | Result to record |
| Payment cancelled | Recoverable state with clear message | Result to record |
| Webhook before browser return | Exactly-once paid actions | Result to record |
| Browser return before webhook | Exactly-once paid actions | Result to record |
| Concurrent webhook/return | Exactly-once paid actions (atomic) | Result to record |
| Duplicate webhook | Safely ignored | Result to record |
| Valid payment then action failure | No accidental duplicate side effects on retry | Result to record |
| Amount or currency mismatch | Paid actions never resume | Result to record |
| Authorized vs captured state | Defined merchant capture policy | Result to record |
| Missing Razorpay credentials | Admin-visible error, no secret leakage | Result to record |
| Per-form vs global credentials | Correct credential set used for each form | Result to record |
| Old pending payment reconciled | Correct credential set and idempotent processing | Result to record |
| Form on JetFormBuilder 3.6.8 | Functional across AJAX/webhook/return | Result to record |
| Multiple actions and conditional logic | Only intended actions execute once | Result to record |
| Webhook malformed JSON | Does not change payment state | Result to record |
| Non-admin reconciliation attempt | Denied | Result to record |

Record environment versions, request IDs, screenshots and logs privately. Never commit customer information or payment credentials.
