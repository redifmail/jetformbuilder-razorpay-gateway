# Staging test matrix (not yet verified)

Test against a supported WordPress install, a current JetFormBuilder version, and Razorpay Test Mode. **Do not accept live payments until the full lifecycle tests are passed.**

| Scenario | Expected behavior | Status |
|---|---|---|
| Clean install and gateway registration | Gateway visible, no PHP fatal | Not run |
| Server-verifiable calculated amount | Correct Razorpay order amount | Not run |
| Browser-tampered amount | Rejected or resolved from trusted server settings | Not run |
| AJAX successful payment | Record completed and each paid action once | Not run |
| Non-AJAX payment | Checkout and return complete properly | Not run |
| Invalid payment signature | Never triggers paid actions | Not run |
| Fake browser failure callback | Must not permanently invalidate a genuine order | Not run |
| Payment cancelled | Recoverable state with clear message | Not run |
| Webhook before browser return | Exactly-once paid actions | Not run |
| Browser return before webhook | Exactly-once paid actions | Not run |
| Concurrent webhook/return | Exactly-once paid actions (atomic) | Not run |
| Duplicate webhook | Safely ignored | Not run |
| Valid payment then action failure | No accidental duplicate side effects on retry | Not run |
| Amount or currency mismatch | Paid actions never resume | Not run |
| Authorized vs captured state | Defined merchant capture policy | Not run |
| Missing Razorpay credentials | Admin-visible error, no secret leakage | Not run |
| Per-form vs global credentials | Correct credential set used for each form | Not run |
| Old pending payment reconciled | Correct credential set and idempotent processing | Not run |
| Form on JetFormBuilder 3.6.8 | Functional across AJAX/webhook/return | Not run |
| Multiple actions and conditional logic | Only intended actions execute once | Not run |
| Webhook malformed JSON | Does not change payment state | Not run |
| Non-admin reconciliation attempt | Denied | Not run |

Record environment versions, request IDs, screenshots and logs privately. Never commit customer information or payment credentials.
